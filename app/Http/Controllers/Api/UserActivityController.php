<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use App\Models\UserRole;

class UserActivityController extends Controller
{
    /* =========================================================================
     |  DASHBOARD: COMPTEURS & STATS (utilisés par le front)
     |=========================================================================*/

    /** GET /api/articles/count  (alias: /api/stats/articles-count) */
    public function articlesCount(): JsonResponse
    {
        $total = Article::query()->count();
        return response()->json(['count' => (int) $total]);
    }

    /** GET /api/users/count  (alias: /api/stats/users-count) */
    public function usersCount(): JsonResponse
    {
        $total = User::query()->count();
        return response()->json(['count' => (int) $total]);
    }

    /** GET /api/stats/users-new?days=30 */
    public function usersNew(Request $request): JsonResponse
    {
        $days  = max(1, (int) $request->get('days', 30));
        $since = Carbon::now()->subDays($days);

        $count = User::query()->where('created_at', '>=', $since)->count();

        return response()->json([
            'count'       => (int) $count,
            'window_days' => $days,
        ]);
    }

    /** GET /api/stats/active-users?days=7 (alias: /api/users/active) */
    public function usersActive(Request $request): JsonResponse
    {
        $days  = max(1, (int) $request->get('days', 7));
        $since = Carbon::now()->subDays($days);

        // utilise last_login_at si présent, sinon fallback sur updated_at
        $table = (new User)->getTable();
        $hasLastLogin = Schema::hasColumn($table, 'last_login_at');

        $q = User::query();
        $hasLastLogin
            ? $q->where('last_login_at', '>=', $since)
            : $q->where('updated_at', '>=', $since);

        return response()->json([
            'count'       => (int) $q->count(),
            'window_days' => $days,
            'field'       => $hasLastLogin ? 'last_login_at' : 'updated_at',
        ]);
    }

    /* =========================================================================
     |  MODÉRATION (commentaires)
     |=========================================================================*/

    /** GET /api/moderation/pending-count */
    public function pendingCount(Request $request): JsonResponse
    {
        $q = Comment::query()->where('status', 'pending');

        if ($request->filled('from')) {
            $q->where('created_at', '>=', $request->get('from') . ' 00:00:00');
        }
        if ($request->filled('to')) {
            $q->where('created_at', '<=', $request->get('to') . ' 23:59:59');
        }

        return response()->json(['pending' => (int) $q->count()]);
    }

    /** GET /api/moderation/pending?per_page=20 */
    public function pendingList(Request $request): JsonResponse
    {
        $perPage = min(max((int)$request->get('per_page', 10), 1), 100);
        $page    = max((int)$request->get('page', 1), 1);

        $q = Comment::query()
            ->with(['article:id,title,slug'])
            ->where('status', 'pending')
            ->orderByDesc('created_at');

        $total = (int) $q->count();
        $last  = (int) ceil(max($total, 1) / $perPage);
        $page  = min($page, max($last, 1));
        $items = $q->forPage($page, $perPage)->get();

        $data = $items->map(function ($c) {
            return [
                'id'           => 'pending-comment-' . $c->id,
                'type'         => 'comment_pending',
                'title'        => 'Commentaire en attente sur « ' . ($c->article->title ?? 'Article') . ' »',
                'subtitle'     => mb_strimwidth(strip_tags((string)$c->content), 0, 120, '…', 'UTF-8'),
                'created_at'   => $c->created_at,
                'article_slug' => $c->article?->slug,
                'comment_id'   => $c->id,
                'url'          => $c->article?->slug ? url("/articles/{$c->article->slug}#comment-{$c->id}") : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $last,
            ],
        ]);
    }

    /* =========================================================================
     |  ACTIVITÉS (feed global + par utilisateur)
     |=========================================================================*/

    /** GET /api/activities */
    public function all(Request $request): JsonResponse
    {
        $perPage   = min(max((int) $request->get('per_page', 10), 1), 100);
        $page      = max((int) $request->get('page', 1), 1);
        $type      = (string) $request->get('type', '');
        $q         = trim((string) $request->get('q', ''));
        $fromDate  = $request->get('from');
        $toDate    = $request->get('to');

        $actorId   = $request->integer('actor_id'); // qui a fait ?
        $targetId  = $request->integer('target_id'); // cible (rôles)
        $userAny   = $request->integer('user'); // impliqué (acteur OU cible)

        $items = collect();

        // 1) rôles attribués
        $roleQuery = UserRole::query()
            ->with([
                'user:id,first_name,last_name,username',
                'role:id,name',
                'assignedBy:id,first_name,last_name,username',
            ])
            ->when($actorId, fn($q) => $q->where('assigned_by', $actorId))
            ->when($targetId, fn($q) => $q->where('user_id', $targetId))
            ->when($userAny, fn($q) => $q->where(function ($qq) use ($userAny) {
                $qq->where('assigned_by', $userAny)->orWhere('user_id', $userAny);
            }))
            ->orderByDesc(DB::raw('COALESCE(assigned_at, created_at)'))
            ->limit(500);

        foreach ($roleQuery->get() as $ur) {
            $by  = $this->displayName($ur->assignedBy);
            $to  = $this->displayName($ur->user);
            $roleName = $ur->role?->name ?? 'Inconnu';

            $items->push([
                'id'         => 'role-' . $ur->id,
                'type'       => 'role_assigned',
                'title'      => sprintf('%s a attribué le rôle « %s » à %s', $by, $roleName, $to),
                'subtitle'   => '',
                'created_at' => $ur->assigned_at ?? $ur->created_at,
                'color'      => 'emerald',
                'actor_id'   => $ur->assigned_by,
                'target_id'  => $ur->user_id,
                'role_name'  => $roleName,
            ]);
        }

        // 2) articles créés
        $articleQuery = Article::query()
            ->select('id', 'title', 'slug', 'created_at', 'published_at', 'created_by')
            ->when($actorId, fn($q) => $q->where('created_by', $actorId))
            ->when($userAny, fn($q) => $q->where('created_by', $userAny))
            ->orderByDesc('created_at')
            ->limit(500);

        foreach ($articleQuery->get() as $a) {
            $items->push([
                'id'           => 'article-' . $a->id,
                'type'         => 'article_created',
                'title'        => sprintf('Article créé : « %s »', $a->title ?: 'Sans titre'),
                'subtitle'     => '',
                'created_at'   => $a->created_at,
                'color'        => 'blue',
                'article_slug' => $a->slug,
                'actor_id'     => $a->created_by,
                'target_id'    => null,
            ]);
        }

        // 3) commentaires approuvés
        $approvalQuery = Comment::query()
            ->with(['article:id,title,slug'])
            ->where('status', 'approved')
            ->when($actorId, fn($q) => $q->where('moderated_by', $actorId))
            ->when($userAny, fn($q) => $q->where('moderated_by', $userAny))
            ->orderByDesc('moderated_at')
            ->limit(500);

        foreach ($approvalQuery->get() as $c) {
            $items->push([
                'id'           => 'comment-approve-' . $c->id,
                'type'         => 'comment_approved',
                'title'        => sprintf('Commentaire approuvé sur « %s »', $c->article?->title ?: 'Article'),
                'subtitle'     => $this->shorten($c->content ?? '', 120),
                'created_at'   => $c->moderated_at ?? $c->updated_at ?? $c->created_at,
                'color'        => 'indigo',
                'article_slug' => $c->article?->slug,
                'comment_id'   => $c->id,
                'actor_id'     => $c->moderated_by,
                'target_id'    => null,
            ]);
        }

        // 4) permission events (si audit présent)
        foreach ($this->fetchPermissionEvents(actorId: $actorId, userAny: $userAny) as $e) {
            $items->push($e);
        }

        // tri/filtre/pagination
        return $this->paginateActivities($items, $type, $q, $fromDate, $toDate, $perPage, $page);
    }

    /** GET /api/users/{user}/activities */
    public function index(Request $request, int $userId): JsonResponse
    {
        $perPage  = min(max((int) $request->get('per_page', 10), 1), 100);
        $page     = max((int) $request->get('page', 1), 1);
        $type     = (string) $request->get('type', '');
        $q        = trim((string) $request->get('q', ''));
        $fromDate = $request->get('from');
        $toDate   = $request->get('to');
        $asTarget = $request->boolean('as_target', false);

        $items = collect();

        // 1) rôles attribués (par défaut: ce user est acteur; avec ?as_target=1: ce user est la cible)
        $roleQuery = UserRole::query()
            ->with([
                'user:id,first_name,last_name,username',
                'role:id,name',
                'assignedBy:id,first_name,last_name,username',
            ])
            ->when($asTarget,
                fn ($q2) => $q2->where('user_id', $userId),
                fn ($q2) => $q2->where('assigned_by', $userId)
            )
            ->orderByDesc(DB::raw('COALESCE(assigned_at, created_at)'))
            ->limit(500);

        foreach ($roleQuery->get() as $ur) {
            $by = $this->displayName($ur->assignedBy);
            $to = $this->displayName($ur->user);
            $roleName = $ur->role?->name ?? 'Inconnu';

            $items->push([
                'id'         => 'role-' . $ur->id,
                'type'       => 'role_assigned',
                'title'      => $asTarget
                    ? sprintf('%s t’a attribué le rôle « %s »', $by, $roleName)
                    : sprintf('%s a attribué le rôle « %s » à %s', $by, $roleName, $to),
                'subtitle'   => '',
                'created_at' => $ur->assigned_at ?? $ur->created_at,
                'color'      => 'emerald',
            ]);
        }

        // 2) articles créés par ce user
        $articleQuery = Article::query()
            ->select('id', 'title', 'slug', 'created_at', 'published_at')
            ->where('created_by', $userId)
            ->orderByDesc('created_at')
            ->limit(500);

        foreach ($articleQuery->get() as $a) {
            $items->push([
                'id'           => 'article-' . $a->id,
                'type'         => 'article_created',
                'title'        => sprintf('Tu as créé l’article « %s »', $a->title ?: 'Sans titre'),
                'subtitle'     => '',
                'created_at'   => $a->created_at,
                'color'        => 'blue',
                'article_slug' => $a->slug,
            ]);
        }

        // 3) commentaires approuvés par ce user
        $approvalQuery = Comment::query()
            ->with(['article:id,title,slug'])
            ->where('moderated_by', $userId)
            ->where('status', 'approved')
            ->orderByDesc('moderated_at')
            ->limit(500);

        foreach ($approvalQuery->get() as $c) {
            $items->push([
                'id'           => 'comment-approve-' . $c->id,
                'type'         => 'comment_approved',
                'title'        => sprintf('Tu as approuvé un commentaire sur « %s »', $c->article?->title ?: 'Article'),
                'subtitle'     => $this->shorten($c->content ?? '', 120),
                'created_at'   => $c->moderated_at ?? $c->updated_at ?? $c->created_at,
                'color'        => 'indigo',
                'article_slug' => $c->article?->slug,
                'comment_id'   => $c->id,
            ]);
        }

        // 4) permission events (si audit présent) pour ce user en tant qu’acteur
        foreach ($this->fetchPermissionEvents(actorId: $userId) as $e) {
            $items->push($e);
        }

        return $this->paginateActivities($items, $type, $q, $fromDate, $toDate, $perPage, $page);
    }

    /* =========================================================================
     |  EFFECTIVE PERMISSIONS (inchangé)
     |=========================================================================*/

    /** GET /api/me/effective-permissions */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        return $this->buildResponseForUserId($user->id);
    }

    /** GET /api/users/{user}/effective-permissions */
    public function show(Request $request, int $user): JsonResponse
    {
        $auth = $request->user();
        abort_unless($auth, 401, 'Unauthenticated');
        if ((int)$auth->id !== (int)$user && !$this->userLooksAdmin($auth)) {
            abort(403, 'Forbidden');
        }

        return $this->buildResponseForUserId($user);
    }

    /* =========================================================================
     |  Utilitaires
     |=========================================================================*/

    private function paginateActivities(
        Collection $items,
        string $type,
        string $q,
        $fromDate,
        $toDate,
        int $perPage,
        int $page
    ): JsonResponse {
        $sorted = $items
            ->filter(fn ($x) => !empty($x['created_at']))
            ->sortByDesc(fn ($x) => strtotime((string) $x['created_at']))
            ->values();

        if ($type !== '') {
            $sorted = $sorted->where('type', $type)->values();
        }

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $sorted = $sorted->filter(function (array $it) use ($needle) {
                $hay = mb_strtolower(($it['title'] ?? '') . ' ' . ($it['subtitle'] ?? ''));
                return mb_strpos($hay, $needle) !== false;
            })->values();
        }

        if ($fromDate) {
            $fromTs = strtotime($fromDate . ' 00:00:00');
            $sorted = $sorted->filter(fn ($it) => strtotime((string) $it['created_at']) >= $fromTs)->values();
        }
        if ($toDate) {
            $toTs = strtotime($toDate . ' 23:59:59');
            $sorted = $sorted->filter(fn ($it) => strtotime((string) $it['created_at']) <= $toTs)->values();
        }

        $total   = $sorted->count();
        $last    = (int) ceil(max($total, 1) / $perPage);
        $page    = min($page, max($last, 1));
        $offset  = ($page - 1) * $perPage;
        $pageSet = $sorted->slice($offset, $perPage)->values();

        return response()->json([
            'data' => $pageSet,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $last,
            ],
        ]);
    }

    private function fetchPermissionEvents(?int $actorId = null, ?int $userAny = null): array
    {
        $out = [];

        // role_permission_audits
        if (Schema::hasTable('role_permission_audits')) {
            $rows = DB::table('role_permission_audits as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
                ->leftJoin('roles as r', 'r.id', '=', 'a.role_id')
                ->when($actorId, fn($q) => $q->where('a.actor_id', $actorId))
                ->when($userAny, fn($q) => $q->where('a.actor_id', $userAny))
                ->orderByDesc('a.created_at')
                ->limit(500)
                ->get([
                    'a.id', 'a.actor_id', 'a.permission_key', 'a.to_value', 'a.created_at',
                    DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), u.username, 'Quelqu’un') as actor_name"),
                    'r.name as role_name',
                ]);

            foreach ($rows as $e) {
                $out[] = $this->mapPermEventRow($e);
            }
            return $out;
        }

        // permission_events
        if (Schema::hasTable('permission_events')) {
            $rows = DB::table('permission_events as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
                ->leftJoin('roles as r', 'r.id', '=', 'a.role_id')
                ->when($actorId, fn($q) => $q->where('a.actor_id', $actorId))
                ->when($userAny, fn($q) => $q->where('a.actor_id', $userAny))
                ->orderByDesc('a.created_at')
                ->limit(500)
                ->get([
                    'a.id', 'a.actor_id', 'a.permission as permission_key', 'a.to as to_value', 'a.created_at',
                    DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), u.username, 'Quelqu’un') as actor_name"),
                    'r.name as role_name',
                ]);

            foreach ($rows as $e) {
                $out[] = $this->mapPermEventRow($e);
            }
            return $out;
        }

        // activity_logs
        if (Schema::hasTable('activity_logs')) {
            $rows = DB::table('activity_logs as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
                ->when($actorId, fn($q) => $q->where('a.actor_id', $actorId))
                ->when($userAny, fn($q) => $q->where('a.actor_id', $userAny))
                ->whereIn('a.event', ['permission.updated', 'permission.changed'])
                ->orderByDesc('a.created_at')
                ->limit(500)
                ->get([
                    'a.id', 'a.actor_id', 'a.created_at',
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(a.properties, '$.permission')) as permission_key"),
                    DB::raw("JSON_EXTRACT(a.properties, '$.to') as to_value"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(a.properties, '$.role.name')) as role_name"),
                    DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), u.username, 'Quelqu’un') as actor_name"),
                ]);

            foreach ($rows as $e) {
                $out[] = $this->mapPermEventRow($e);
            }
        }

        return $out;
    }

    private function mapPermEventRow($e): array
    {
        $actor = $e->actor_name ?: 'Quelqu’un';
        $role  = $e->role_name ?: 'Inconnu';
        $toBool = $this->toBool($e->to_value);
        $perm   = $e->permission_key ?: '—';

        return [
            'id'         => 'perm-' . $e->id,
            'type'       => 'permission_changed',
            'title'      => sprintf("%s a modifié les permissions du rôle « %s »", $actor, $role),
            'subtitle'   => sprintf("Permission: %s → %s", $perm, $toBool ? 'Activé' : 'Désactivé'),
            'created_at' => $e->created_at,
            'color'      => 'violet',
            'actor_id'   => $e->actor_id ?? null,
            'target_id'  => null,
        ];
    }

    private function displayName($user): string
    {
        if (!$user) return 'Quelqu’un';
        $full = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $full !== '' ? $full : ($user->username ?? 'Quelqu’un');
    }

    private function shorten(string $text, int $limit = 120): string
    {
        $t = trim(strip_tags($text));
        return mb_strlen($t) > $limit ? (mb_substr($t, 0, $limit - 1) . '…') : $t;
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int) $value) === 1;
        $v = mb_strtolower((string) $value);
        return in_array($v, ['1', 'true', 'vrai', 'yes', 'oui'], true);
    }

    /* ===== Effective permissions (inchangé) ===== */

    private function buildResponseForUserId(int $userId): JsonResponse
    {
        $roles = DB::table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->get(['r.id', 'r.name']);

        $roleIds = $roles->pluck('id')->all();

        $permByRole = collect();
        if (!empty($roleIds)) {
            $permByRole = DB::table('role_permissions as rp')
                ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                ->whereIn('rp.role_id', $roleIds)
                ->pluck('p.action');
        }

        $permByUser = collect();
        if (Schema::hasTable('user_permissions')) {
            $permByUser = DB::table('user_permissions as up')
                ->join('permissions as p', 'p.id', '=', 'up.permission_id')
                ->where('up.user_id', $userId)
                ->pluck('p.action');
        }

        $allActionKeys = $permByRole->merge($permByUser)->unique()->values();

        $canonical = ['create','read','update','delete'];
        $resourceSet = [];
        foreach ($allActionKeys as $key) {
            $parts = explode('.', (string) $key, 2);
            if (count($parts) === 2) {
                [$res, $act] = $parts;
                $resourceSet[$res][$act] = true;
            }
        }

        $resources = array_keys($resourceSet);
        sort($resources, SORT_NATURAL);

        $grants = [];
        $flat = [];
        foreach ($resources as $res) {
            foreach ($canonical as $act) {
                $has = !empty($resourceSet[$res][$act]);
                $grants[$res][$act] = (bool) $has;
                if ($has) $flat[] = "{$res}.{$act}";
            }
        }

        return response()->json([
            'user_id'   => $userId,
            'roles'     => $roles,
            'actions'   => $canonical,
            'resources' => $resources,
            'grants'    => $grants,
            'flat_keys' => $flat,
        ]);
    }

    private function userLooksAdmin($user): bool
    {
        try {
            if (Schema::hasTable('roles') && Schema::hasTable('user_roles')) {
                return DB::table('user_roles as ur')
                    ->join('roles as r', 'r.id', '=', 'ur.role_id')
                    ->where('ur.user_id', $user->id)
                    ->whereIn(DB::raw('LOWER(r.name)'), ['admin','administrator','super admin','superadmin','super-administrator'])
                    ->exists();
            }
        } catch (\Throwable $e) {}
        return false;
    }

    /** GET /api/csrf (optionnel) */
    public function showcsrf(Request $request)
    {
        // Laravel génère/renouvelle automatiquement le token de session
        $token = csrf_token();

        $cookie = cookie(
            name:    'XSRF-TOKEN',
            value:   $token,
            minutes: 120,
            path:    '/',
            domain:  config('session.domain'),
            secure:  (bool) config('session.secure', false),
            httpOnly:false,
            raw:     false,
            sameSite:'Lax'
        );

        return response()->noContent()->withCookie($cookie);
    }

    /**
     * GET /api/stats/time-series
     * Params: metric=comments|views|shares, days, article_id, tenant_id, status (comments)
     */
    public function timeSeries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metric'     => 'required|string|in:comments,views,shares',
            'days'       => 'nullable|integer|min:1|max:365',
            'article_id' => 'nullable|integer',
            'tenant_id'  => 'nullable|integer',
            'status'     => 'nullable|string|in:pending,approved,rejected,spam', // pour comments
        ]);

        $metric = $validated['metric'];
        $days   = (int)($validated['days'] ?? 30);
        $to     = Carbon::today();
        $from   = (clone $to)->subDays($days - 1);

        // Helper séries => DB -> {day => count} -> fill zeroes
        $fill = function(array $kv) use ($from, $to): array {
            $map = [];
            $cursor = (clone $from);
            while ($cursor->lte($to)) {
                $map[$cursor->toDateString()] = 0;
                $cursor->addDay();
            }
            foreach ($kv as $row) {
                $map[$row->day] = (int) $row->count;
            }
            return collect($map)->map(fn($v, $k) => ['day' => $k, 'count' => $v])->values()->all();
        };

        // ------- COMMENTS -------
        if ($metric === 'comments') {
            $q = DB::table('comments')
                ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                ->whereBetween('created_at', [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59']);

            $status = $validated['status'] ?? 'approved';
            $q->where('status', $status);

            if (!empty($validated['article_id']) && Schema::hasColumn('comments', 'article_id')) {
                $q->where('article_id', (int)$validated['article_id']);
            }
            if (!empty($validated['tenant_id']) && Schema::hasColumn('comments', 'tenant_id'))  {
                $q->where('tenant_id', (int)$validated['tenant_id']);
            }

            $rows = $q->groupBy('day')->orderBy('day')->get();
            return response()->json(['series' => $fill($rows->all())]);
        }

        // ------- VIEWS -------
        if ($metric === 'views') {
            if (Schema::hasTable('article_views')) {
                $hasDate  = Schema::hasColumn('article_views', 'date');
                $hasCount = Schema::hasColumn('article_views', 'count');

                if ($hasDate && $hasCount) {
                    $q = DB::table('article_views')
                        ->selectRaw('`date` as day, SUM(`count`) as count')
                        ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
                    if (!empty($validated['article_id']) && Schema::hasColumn('article_views', 'article_id')) $q->where('article_id', (int)$validated['article_id']);
                    if (!empty($validated['tenant_id'])  && Schema::hasColumn('article_views', 'tenant_id'))  $q->where('tenant_id', (int)$validated['tenant_id']);
                    $rows = $q->groupBy('day')->orderBy('day')->get();
                    return response()->json(['series' => $fill($rows->all())]);
                }

                // fallback événementiel sur la même table
                $q = DB::table('article_views')
                    ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                    ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()]);
                if (!empty($validated['article_id']) && Schema::hasColumn('article_views', 'article_id')) $q->where('article_id', (int)$validated['article_id']);
                if (!empty($validated['tenant_id'])  && Schema::hasColumn('article_views', 'tenant_id'))  $q->where('tenant_id', (int)$validated['tenant_id']);
                $rows = $q->groupBy('day')->orderBy('day')->get();
                return response()->json(['series' => $fill($rows->all())]);
            }

            if (Schema::hasTable('article_events')) {
                $q = DB::table('article_events')
                    ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                    ->where('type', 'view')
                    ->whereBetween('created_at', [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59']);
                if (!empty($validated['article_id']) && Schema::hasColumn('article_events', 'article_id')) $q->where('article_id', (int)$validated['article_id']);
                if (!empty($validated['tenant_id'])  && Schema::hasColumn('article_events', 'tenant_id'))  $q->where('tenant_id', (int)$validated['tenant_id']);
                $rows = $q->groupBy('day')->orderBy('day')->get();
                return response()->json(['series' => $fill($rows->all())]);
            }

            return response()->json(['series' => $fill([])]);
        }

        // ------- SHARES -------
        if ($metric === 'shares') {
            if (Schema::hasTable('article_shares')) {
                $hasDate  = Schema::hasColumn('article_shares', 'date');
                $hasCount = Schema::hasColumn('article_shares', 'count');

                if ($hasDate && $hasCount) {
                    $q = DB::table('article_shares')
                        ->selectRaw('`date` as day, SUM(`count`) as count')
                        ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
                    if (!empty($validated['article_id']) && Schema::hasColumn('article_shares', 'article_id')) $q->where('article_id', (int)$validated['article_id']);
                    if (!empty($validated['tenant_id'])  && Schema::hasColumn('article_shares', 'tenant_id'))  $q->where('tenant_id', (int)$validated['tenant_id']);
                    $rows = $q->groupBy('day')->orderBy('day')->get();
                    return response()->json(['series' => $fill($rows->all())]);
                }

                // fallback événementiel sur la même table
                $q = DB::table('article_shares')
                    ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                    ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()]);
                if (!empty($validated['article_id']) && Schema::hasColumn('article_shares', 'article_id')) $q->where('article_id', (int)$validated['article_id']);
                if (!empty($validated['tenant_id'])  && Schema::hasColumn('article_shares', 'tenant_id'))  $q->where('tenant_id', (int)$validated['tenant_id']);
                $rows = $q->groupBy('day')->orderBy('day')->get();
                return response()->json(['series' => $fill($rows->all())]);
            }

            if (Schema::hasTable('article_events')) {
                $q = DB::table('article_events')
                    ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                    ->where('type', 'share')
                    ->whereBetween('created_at', [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59']);
                if (!empty($validated['article_id']) && Schema::hasColumn('article_events', 'article_id')) $q->where('article_id', (int)$validated['article_id']);
                if (!empty($validated['tenant_id'])  && Schema::hasColumn('article_events', 'tenant_id'))  $q->where('tenant_id', (int)$validated['tenant_id']);
                $rows = $q->groupBy('day')->orderBy('day')->get();
                return response()->json(['series' => $fill($rows->all())]);
            }

            return response()->json(['series' => $fill([])]);
        }

        return response()->json(['series' => $fill([])]);
    }

    /**
     * GET /api/stats/trending
     * Ex: ?metric=comments&days=30&limit=6
     * Retour: [{article_id, count}]
     */
    public function trending(Request $request): JsonResponse
{
    $validated = $request->validate([
        'metric'     => 'required|string|in:comments,views,shares',
        'days'       => 'nullable|integer|min:1|max:365',
        'limit'      => 'nullable|integer|min:1|max:50',
        'tenant_id'  => 'nullable|integer',
        'status'     => 'nullable|string|in:pending,approved,rejected,spam', // pour comments
    ]);

    $metric = $validated['metric'];
    $days   = (int)($validated['days'] ?? 30);
    $limit  = (int)($validated['limit'] ?? 6);
    $to     = Carbon::now();
    $from   = (clone $to)->subDays($days);

    // ------- COMMENTS -------
    if ($metric === 'comments') {
        $q = DB::table('comments as c')
            ->join('articles as a', 'a.id', '=', 'c.article_id')
            ->selectRaw('c.article_id, a.title, a.slug, COUNT(*) as count')
            ->whereNotNull('c.article_id')
            ->whereBetween('c.created_at', [$from, $to]);

        $status = $validated['status'] ?? 'approved';
        $q->where('c.status', $status);

        if (!empty($validated['tenant_id']) && Schema::hasColumn('comments', 'tenant_id')) {
            $q->where('c.tenant_id', (int)$validated['tenant_id']);
        }

        $rows = $q->groupBy('c.article_id', 'a.title', 'a.slug')
                  ->orderByDesc('count')
                  ->limit($limit)
                  ->get();

        return response()->json(['data' => $rows]);
    }

    // ------- VIEWS -------
    if ($metric === 'views') {
        // Table dédiée "article_views"
        if (Schema::hasTable('article_views')) {
            $hasDate  = Schema::hasColumn('article_views', 'date');
            $hasCount = Schema::hasColumn('article_views', 'count');

            // Cas "agrégé" (date/count)
            if ($hasDate && $hasCount) {
                $base = DB::table('article_views as v')
                    ->join('articles as a', 'a.id', '=', 'v.article_id')
                    ->whereBetween('v.date', [$from->toDateString(), $to->toDateString()])
                    ->selectRaw('v.article_id, a.title, a.slug, SUM(v.count) as count');

                if (!empty($validated['tenant_id']) && Schema::hasColumn('article_views', 'tenant_id')) {
                    $base->where('v.tenant_id', (int)$validated['tenant_id']);
                }

                $rows = $base->groupBy('v.article_id', 'a.title', 'a.slug')
                             ->orderByDesc('count')
                             ->limit($limit)
                             ->get();

                return response()->json(['data' => $rows]);
            }

            // Cas "événementiel" fallback (created_at)
            $rows = DB::table('article_views as v')
                ->join('articles as a', 'a.id', '=', 'v.article_id')
                ->whereBetween('v.created_at', [$from, $to])
                ->when(!empty($validated['tenant_id']) && Schema::hasColumn('article_views', 'tenant_id'),
                    fn($q) => $q->where('v.tenant_id', (int)$validated['tenant_id']))
                ->selectRaw('v.article_id, a.title, a.slug, COUNT(*) as count')
                ->groupBy('v.article_id', 'a.title', 'a.slug')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();

            return response()->json(['data' => $rows]);
        }

        // Fallback "article_events" (type = view)
        if (Schema::hasTable('article_events')) {
            $rows = DB::table('article_events as e')
                ->join('articles as a', 'a.id', '=', 'e.article_id')
                ->where('e.type', 'view')
                ->whereBetween('e.created_at', [$from, $to])
                ->when(!empty($validated['tenant_id']) && Schema::hasColumn('article_events', 'tenant_id'),
                    fn($q) => $q->where('e.tenant_id', (int)$validated['tenant_id']))
                ->selectRaw('e.article_id, a.title, a.slug, COUNT(*) as count')
                ->groupBy('e.article_id', 'a.title', 'a.slug')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();

            return response()->json(['data' => $rows]);
        }

        return response()->json(['data' => []]);
    }

    // ------- SHARES -------
    if ($metric === 'shares') {
        // Table dédiée "article_shares"
        if (Schema::hasTable('article_shares')) {
            $hasDate  = Schema::hasColumn('article_shares', 'date');
            $hasCount = Schema::hasColumn('article_shares', 'count');

            // Cas "agrégé" (date/count)
            if ($hasDate && $hasCount) {
                $base = DB::table('article_shares as s')
                    ->join('articles as a', 'a.id', '=', 's.article_id')
                    ->whereBetween('s.date', [$from->toDateString(), $to->toDateString()])
                    ->selectRaw('s.article_id, a.title, a.slug, SUM(s.count) as count');

                if (!empty($validated['tenant_id']) && Schema::hasColumn('article_shares', 'tenant_id')) {
                    $base->where('s.tenant_id', (int)$validated['tenant_id']);
                }

                $rows = $base->groupBy('s.article_id', 'a.title', 'a.slug')
                             ->orderByDesc('count')
                             ->limit($limit)
                             ->get();

                return response()->json(['data' => $rows]);
            }

            // Cas "événementiel" fallback (created_at)
            $rows = DB::table('article_shares as s')
                ->join('articles as a', 'a.id', '=', 's.article_id')
                ->whereBetween('s.created_at', [$from, $to])
                ->when(!empty($validated['tenant_id']) && Schema::hasColumn('article_shares', 'tenant_id'),
                    fn($q) => $q->where('s.tenant_id', (int)$validated['tenant_id']))
                ->selectRaw('s.article_id, a.title, a.slug, COUNT(*) as count')
                ->groupBy('s.article_id', 'a.title', 'a.slug')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();

            return response()->json(['data' => $rows]);
        }

        // Fallback "article_events" (type = share)
        if (Schema::hasTable('article_events')) {
            $rows = DB::table('article_events as e')
                ->join('articles as a', 'a.id', '=', 'e.article_id')
                ->where('e.type', 'share')
                ->whereBetween('e.created_at', [$from, $to])
                ->when(!empty($validated['tenant_id']) && Schema::hasColumn('article_events', 'tenant_id'),
                    fn($q) => $q->where('e.tenant_id', (int)$validated['tenant_id']))
                ->selectRaw('e.article_id, a.title, a.slug, COUNT(*) as count')
                ->groupBy('e.article_id', 'a.title', 'a.slug')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();

            return response()->json(['data' => $rows]);
        }

        return response()->json(['data' => []]);
    }

    return response()->json(['data' => []]);
}


    /**
 * GET /api/stats/downloads/time-series
 * Statistiques de téléchargements des médias d'articles
 */
public function downloadsTimeSeries(Request $request): JsonResponse
{
    $validated = $request->validate([
        'days'              => 'nullable|integer|min:1|max:365',
        'include_protected' => 'nullable|boolean',
        'article_id'        => 'nullable|integer',
        'tenant_id'         => 'nullable|integer',
    ]);

    $days             = (int)($validated['days'] ?? 14);
    $includeProtected = (bool)($validated['include_protected'] ?? false);
    $to   = Carbon::today();
    $from = (clone $to)->subDays($days - 1);

    $fill = function(array $kv) use ($from, $to): array {
        $map = [];
        $cursor = (clone $from);
        while ($cursor->lte($to)) {
            $map[$cursor->toDateString()] = 0;
            $cursor->addDay();
        }
        foreach ($kv as $row) {
            $map[$row->day] = (int) $row->count;
        }
        return collect($map)->map(fn($v, $k) => ['day' => $k, 'count' => $v])->values()->all();
    };

    // tables candidates pour les téléchargements
    $dlTables = ['article_media_downloads', 'textes_complets', 'media_downloads', 'file_downloads'];
    $table = collect($dlTables)->first(fn($t) => Schema::hasTable($t));

    // table media pour joindre les métadonnées (protected, article_id, tenant, etc.)
    $mediaTables = ['article_media', 'media_files', 'files', 'media'];
    $mediaTable = collect($mediaTables)->first(fn($t) => Schema::hasTable($t));

    if ($table) {
        $hasDate     = Schema::hasColumn($table, 'date');
        $hasCountCol = Schema::hasColumn($table, 'count');
        $hasCreated  = Schema::hasColumn($table, 'created_at');

        // ===== 1) Agrégé jour (date/count) =====
        if ($hasDate && $hasCountCol) {
            $q = DB::table($table)
                ->selectRaw('`date` as day, SUM(`count`) as count')
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

            // filtre article_id via jointure media (si possible)
            if (!empty($validated['article_id']) && $mediaTable && Schema::hasColumn($mediaTable, 'article_id')) {
                $q->join("$mediaTable as am", "am.id", "=", "$table.file_id")
                  ->where('am.article_id', (int)$validated['article_id']);
            }

            if (!empty($validated['tenant_id']) && Schema::hasColumn($table, 'tenant_id')) {
                $q->where("$table.tenant_id", (int)$validated['tenant_id']);
            }

            if (!$includeProtected && $mediaTable) {
                $q->join("$mediaTable as mp", "mp.id", "=", "$table.file_id");
                if (Schema::hasColumn($mediaTable, 'is_protected')) {
                    $q->where('mp.is_protected', false);
                } elseif (Schema::hasColumn($mediaTable, 'protected')) {
                    $q->where('mp.protected', false);
                } elseif (Schema::hasColumn($mediaTable, 'password') || Schema::hasColumn($mediaTable, 'password_hash')) {
                    $q->whereNull('mp.password')->whereNull('mp.password_hash');
                } elseif (Schema::hasColumn($mediaTable, 'visibility')) {
                    $q->where('mp.visibility', '!=', 'password_protected');
                }
            }

            $rows = $q->groupBy('day')->orderBy('day')->get();
            return response()->json(['series' => $fill($rows->all())]);
        }

        // ===== 2) Événementiel (created_at) =====
        if ($hasCreated) {
            $q = DB::table($table)
                ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                ->whereBetween('created_at', [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59']);

            if (!empty($validated['article_id']) && $mediaTable && Schema::hasColumn($mediaTable, 'article_id')) {
                $q->join("$mediaTable as am", "am.id", "=", "$table.file_id")
                  ->where('am.article_id', (int)$validated['article_id']);
            }

            if (!empty($validated['tenant_id']) && Schema::hasColumn($table, 'tenant_id')) {
                $q->where("$table.tenant_id", (int)$validated['tenant_id']);
            }

            if (!$includeProtected && $mediaTable) {
                $q->join("$mediaTable as mp", "mp.id", "=", "$table.file_id");
                if (Schema::hasColumn($mediaTable, 'is_protected')) {
                    $q->where('mp.is_protected', false);
                } elseif (Schema::hasColumn($mediaTable, 'protected')) {
                    $q->where('mp.protected', false);
                } elseif (Schema::hasColumn($mediaTable, 'password') || Schema::hasColumn($mediaTable, 'password_hash')) {
                    $q->whereNull('mp.password')->whereNull('mp.password_hash');
                } elseif (Schema::hasColumn($mediaTable, 'visibility')) {
                    $q->where('mp.visibility', '!=', 'password_protected');
                }
            }

            $rows = $q->groupBy('day')->orderBy('day')->get();
            return response()->json(['series' => $fill($rows->all())]);
        }
    }

    // ===== 3) Fallback: article_events (type=download) =====
    if (Schema::hasTable('article_events')) {
        $q = DB::table('article_events')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('type', 'download')
            ->whereBetween('created_at', [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59']);

        if (!empty($validated['article_id']) && Schema::hasColumn('article_events', 'article_id')) {
            $q->where('article_id', (int)$validated['article_id']);
        }
        if (!empty($validated['tenant_id']) && Schema::hasColumn('article_events', 'tenant_id')) {
            $q->where('tenant_id', (int)$validated['tenant_id']);
        }

        $rows = $q->groupBy('day')->orderBy('day')->get();
        return response()->json(['series' => $fill($rows->all())]);
    }

    return response()->json(['series' => $fill([])]);
}

}
