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
        // si besoin : restreindre aux modérateurs
        // $user = $request->user(); abort_unless($user, 401);
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
        // $user = $request->user(); abort_unless($user, 401);
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
        $asTarget  = $request->boolean('as_target', false);

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
     |  EFFECTIVE PERMISSIONS (déjà présents dans ton code)
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
     |  Helpers internes
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

    /* ===== Effective permissions (inchangé, repris de ton code) ===== */

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

    public function showcsrf(Request $request)
    {
        // Laravel génère/renouvelle automatiquement le token de session
        $token = csrf_token();

        // Cookie XSRF-TOKEN (lisible JS => httpOnly=false), samesite=Lax
        $cookie = cookie(
            name:    'XSRF-TOKEN',
            value:   $token,
            minutes: 120,
            path:    '/',
            domain:  config('session.domain'),
            secure:  (bool) config('session.secure', false),
            httpOnly:false,    // IMPORTANT : lisible par le navigateur
            raw:     false,
            sameSite:'Lax'
        );

        // Réponse 204 No Content + cookie
        return response()->noContent()->withCookie($cookie);
    }
}
