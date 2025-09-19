<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use App\Models\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserActivityController extends Controller
{
    /**
     * GET /api/users/{user}/activities
     *
     * Unifie les activités :
     *  - Rôles attribués (UserRole.assigned_by = {user}) ou reçus (user_id = {user} si ?as_target=1)
     *  - Articles créés (Article.created_by = {user})  → renvoie article_slug
     *  - Commentaires approuvés (Comment.moderated_by = {user}, status=approved) → renvoie article_slug + comment_id
     *  - Permissions modifiées (si table d’audit existante) → titre + sous-titre normalisés
     *
     * Filtres (query params) :
     *  - page: int>=1         (def: 1)
     *  - per_page: 1..100     (def: 10)
     *  - type: string in [permission_changed, role_assigned, article_created, comment_approved]
     *  - q: string (recherche sur title + subtitle)
     *  - from: YYYY-MM-DD
     *  - to:   YYYY-MM-DD
     *  - as_target: bool (1/0)  → si 1 : on liste les rôles attribués AU user, sinon PAR le user
     */
    public function index(Request $request, int $userId): JsonResponse
    {
        // Pagination et filtres basiques
        $perPage  = min(max((int) $request->get('per_page', 10), 1), 100);
        $page     = max((int) $request->get('page', 1), 1);
        $type     = (string) $request->get('type', '');
        $q        = trim((string) $request->get('q', ''));
        $fromDate = $request->get('from');
        $toDate   = $request->get('to');
        $asTarget = $request->boolean('as_target', false);

        $items = collect();

        // ---------------------------------------------------------------------
        // 1) Attributions de rôles
        // ---------------------------------------------------------------------
        $roleQuery = UserRole::query()
            ->with([
                'user:id,first_name,last_name,username',
                'role:id,name',
                'assignedBy:id,first_name,last_name,username',
            ])
            ->when($asTarget,
                fn ($q) => $q->where('user_id', $userId),
                fn ($q) => $q->where('assigned_by', $userId)
            )
            ->orderByDesc(DB::raw('COALESCE(assigned_at, created_at)'))
            ->limit(500);

        foreach ($roleQuery->get() as $ur) {
            $byUser = $ur->assignedBy;
            $toUser = $ur->user;

            $by = $this->displayName($byUser);
            $to = $this->displayName($toUser);

            $roleName = $ur->role?->name ?? 'Inconnu';
            $title = $asTarget
                ? sprintf('%s t’a attribué le rôle « %s »', $by, $roleName)
                : sprintf('%s a attribué le rôle « %s » à %s', $by, $roleName, $to);

            $items->push([
                'id'         => 'role-'.$ur->id,
                'type'       => 'role_assigned',
                'title'      => $title,
                'subtitle'   => '',
                'created_at' => $ur->assigned_at ?? $ur->created_at,
                'color'      => 'emerald',
                // 'url'     => url('/settings'), // optionnel si tu veux envoyer l’URL directe
            ]);
        }

        // ---------------------------------------------------------------------
        // 2) Articles créés → inclure le slug
        // ---------------------------------------------------------------------
        $articleQuery = Article::query()
            ->select('id', 'title', 'slug', 'created_at', 'published_at', 'created_by')
            ->where('created_by', $userId)
            ->orderByDesc('created_at')
            ->limit(500);

        foreach ($articleQuery->get() as $a) {
            $items->push([
                'id'           => 'article-'.$a->id,
                'type'         => 'article_created',
                'title'        => sprintf('Tu as créé l’article « %s »', $a->title ?: 'Sans titre'),
                'subtitle'     => '',
                'created_at'   => $a->created_at,
                'color'        => 'blue',
                'article_slug' => $a->slug,
                // 'url'        => url("/articles/{$a->slug}") // optionnel
            ]);
        }

        // ---------------------------------------------------------------------
        // 3) Commentaires approuvés → article slug + comment_id
        // ---------------------------------------------------------------------
        $approvalQuery = Comment::query()
            ->with(['article:id,title,slug'])
            ->where('moderated_by', $userId)
            ->where('status', 'approved')
            ->orderByDesc('moderated_at')
            ->limit(500);

        foreach ($approvalQuery->get() as $c) {
            $items->push([
                'id'           => 'comment-approve-'.$c->id,
                'type'         => 'comment_approved',
                'title'        => sprintf('Tu as approuvé un commentaire sur « %s »', $c->article?->title ?: 'Article'),
                'subtitle'     => $this->shorten($c->content ?? '', 120),
                'created_at'   => $c->moderated_at ?? $c->updated_at ?? $c->created_at,
                'color'        => 'indigo',
                'article_slug' => $c->article?->slug,
                'comment_id'   => $c->id,
                // 'url'        => $c->article?->slug ? url("/articles/{$c->article->slug}#comment-{$c->id}") : null
            ]);
        }

        // ---------------------------------------------------------------------
        // 4) Permission events (si des tables d’audit existent)
        // ---------------------------------------------------------------------
        $permissionEvents = collect();

        if (Schema::hasTable('role_permission_audits')) {
            $permissionEvents = DB::table('role_permission_audits as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
                ->leftJoin('roles as r', 'r.id', '=', 'a.role_id')
                ->where('a.actor_id', $userId)
                ->orderByDesc('a.created_at')
                ->limit(500)
                ->get([
                    'a.id',
                    'a.permission_key',
                    'a.to_value',
                    'a.created_at',
                    DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), u.username, 'Quelqu’un') as actor_name"),
                    'r.name as role_name',
                ]);
        } elseif (Schema::hasTable('permission_events')) {
            $permissionEvents = DB::table('permission_events as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
                ->leftJoin('roles as r', 'r.id', '=', 'a.role_id')
                ->where('a.actor_id', $userId)
                ->orderByDesc('a.created_at')
                ->limit(500)
                ->get([
                    'a.id',
                    'a.permission as permission_key',
                    'a.to as to_value',
                    'a.created_at',
                    DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), u.username, 'Quelqu’un') as actor_name"),
                    'r.name as role_name',
                ]);
        } elseif (Schema::hasTable('activity_logs')) {
            // Ex: activity_logs(actor_id, event, properties JSON, created_at)
            $permissionEvents = DB::table('activity_logs as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
                ->where('a.actor_id', $userId)
                ->whereIn('a.event', ['permission.updated', 'permission.changed'])
                ->orderByDesc('a.created_at')
                ->limit(500)
                ->get([
                    'a.id',
                    'a.created_at',
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(a.properties, '$.permission')) as permission_key"),
                    DB::raw("JSON_EXTRACT(a.properties, '$.to') as to_value"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(a.properties, '$.role.name')) as role_name"),
                    DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), u.username, 'Quelqu’un') as actor_name"),
                ]);
        }

        foreach ($permissionEvents as $e) {
            $actor = $e->actor_name ?: 'Quelqu’un';
            $role  = $e->role_name ?: 'Inconnu';
            $toBool = $this->toBool($e->to_value);
            $perm   = $e->permission_key ?: '—';

            $items->push([
                'id'         => 'perm-'.$e->id,
                'type'       => 'permission_changed',
                'title'      => sprintf("%s a modifié les permissions du rôle « %s »", $actor, $role),
                'subtitle'   => sprintf("Permission: %s → %s", $perm, $toBool ? 'Activé' : 'Désactivé'),
                'created_at' => $e->created_at,
                'color'      => 'violet',
                // 'url'     => url('/settings') // optionnel
            ]);
        }

        // ---------------------------------------------------------------------
        // Tri + Filtres (type, q, from, to) + Pagination manuelle
        // ---------------------------------------------------------------------
        $sorted = $items
            ->filter(fn ($x) => !empty($x['created_at']))
            ->sortByDesc(fn ($x) => strtotime((string) $x['created_at']))
            ->values();

        // Filtre type
        if ($type !== '') {
            $sorted = $sorted->where('type', $type)->values();
        }

        // Filtre q (case-insensitive sur title + subtitle)
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $sorted = $sorted->filter(function (array $it) use ($needle) {
                $hay = mb_strtolower(($it['title'] ?? '') . ' ' . ($it['subtitle'] ?? ''));
                return mb_strpos($hay, $needle) !== false;
            })->values();
        }

        // Filtre date from/to (sur created_at)
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    private function displayName($user): string
    {
        if (!$user) return 'Quelqu’un';
        $full = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        return $full !== '' ? $full : ($user->username ?? 'Quelqu’un');
    }

    private function shorten(string $text, int $limit = 120): string
    {
        $t = trim(strip_tags($text));
        return mb_strlen($t) > $limit ? (mb_substr($t, 0, $limit - 1).'…') : $t;
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int) $value) === 1;
        $v = mb_strtolower((string) $value);
        return in_array($v, ['1', 'true', 'vrai', 'yes', 'oui'], true);
    }
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
        // Autorisation basique (à adapter selon ta politique d’accès)
        // Ici : on autorise l’auto-lecture et les admins (si table/colonne existe)
        $auth = $request->user();
        abort_unless($auth, 401, 'Unauthenticated');
        if ((int)$auth->id !== (int)$user && !$this->userLooksAdmin($auth)) {
            abort(403, 'Forbidden');
        }

        return $this->buildResponseForUserId($user);
    }

    // ---------------------------------------------------------------------
    // Core
    // ---------------------------------------------------------------------

    private function buildResponseForUserId(int $userId): JsonResponse
    {
        // Rôles de l’utilisateur
        // Tables courantes : user_roles(user_id, role_id), roles(id, name)
        $roles = DB::table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->get(['r.id', 'r.name']);

        $roleIds = $roles->pluck('id')->all();

        // Permissions liées aux rôles
        // Tables courantes : role_permissions(role_id, permission_id), permissions(id, action)
        // Convention “action” = "resource.action" (ex: "articles.create")
        $permByRole = collect();
        if (!empty($roleIds)) {
            $permByRole = DB::table('role_permissions as rp')
                ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                ->whereIn('rp.role_id', $roleIds)
                ->pluck('p.action'); // ex: "articles.create"
        }

        // Permissions directes utilisateur (optionnel)
        // Table facultative: user_permissions(user_id, permission_id)
        $permByUser = collect();
        if (Schema::hasTable('user_permissions')) {
            $permByUser = DB::table('user_permissions as up')
                ->join('permissions as p', 'p.id', '=', 'up.permission_id')
                ->where('up.user_id', $userId)
                ->pluck('p.action');
        }

        // Union des clés d’actions
        $allActionKeys = $permByRole->merge($permByUser)->unique()->values();

        // Déduire resources & actions (colonnes)
        // ACTIONS canoniques côté front (tu peux les modifier si besoin)
        $canonicalActions = ['create','read','update','delete'];

        $resourceSet = [];
        foreach ($allActionKeys as $key) {
            // key = "resource.action"
            $parts = explode('.', (string) $key, 2);
            if (count($parts) === 2) {
                [$res, $act] = $parts;
                if (!isset($resourceSet[$res])) $resourceSet[$res] = [];
                $resourceSet[$res][$act] = true; // présent
            }
        }

        // Construire grants complet : chaque res a les 4 actions, true/false
        $resources = array_keys($resourceSet);
        sort($resources, SORT_NATURAL);

        $grants = [];
        $flat = [];
        foreach ($resources as $res) {
            $grants[$res] = [];
            foreach ($canonicalActions as $act) {
                $has = !empty($resourceSet[$res][$act]);
                $grants[$res][$act] = (bool) $has;
                if ($has) $flat[] = "{$res}.{$act}";
            }
        }

        return response()->json([
            'user_id'  => $userId,
            'roles'    => $roles,
            'actions'  => $canonicalActions,
            'resources'=> $resources,
            'grants'   => $grants,
            'flat_keys'=> $flat,
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
        } catch (\Throwable $e) {
            // ignore
        }
        return false;
    }
}
