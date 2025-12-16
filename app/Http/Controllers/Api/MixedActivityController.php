<?php
// app/Http/Controllers/Api/MixedActivityController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Activity; // ta table notifications perso
use App\Models\Article;
use App\Models\Comment;
use App\Models\UserRole;
use App\Models\ContactMessage;
use App\Support\UserRoles;

class MixedActivityController extends Controller
{
    public function index(Request $request, int $userId)
    {
        $auth = $request->user();
        abort_unless($auth, 401, 'Unauthenticated');

        // Un user ne peut consulter QUE son propre feed,
        // sauf si c’est un admin qui veut regarder quelqu’un d’autre.
        if ((int) $auth->id !== (int) $userId && !UserRoles::isAdmin($auth)) {
            abort(403, 'Forbidden');
        }

        $perPage   = min(max((int) $request->get('per_page', 10), 1), 100);
        $page      = max((int) $request->get('page', 1), 1);
        $type      = (string) $request->get('type', '');
        $q         = trim((string) $request->get('q', ''));
        $fromDate  = $request->get('from');
        $toDate    = $request->get('to');
        $asTarget  = $request->boolean('as_target', false);

        // Privilèges globaux (admin ou modérateur pour le flux global)
        $isPrivileged = UserRoles::isAdmin($auth) || UserRoles::isModerator($auth);
        // Flag spécifiquement pour les admins (pour les messages de contact)
        $isAdminOnly  = UserRoles::isAdmin($auth);

        $items = collect();

        // === Flux 1 : notifications perso depuis `activities` (toujours visibles à l’utilisateur)
        $items = $items->merge(
            $this->mapPersonalActivities($userId, $fromDate, $toDate, $type, $q, $perPage * 5) // suréchantillonne un peu
        );

        // === Flux 2 : agrégateur global (uniquement si admin/moderator)
        if ($isPrivileged) {
            $items = $items->merge(
                $this->mapGlobalFeed($userId, $asTarget, $fromDate, $toDate, $isAdminOnly)
            );
        }

        // Tri + filtres texte/type/dates (déjà partiellement faits sur perso)
        $items = $items
            ->filter(fn($x) => !empty($x['created_at']))
            ->when($type !== '', fn($c) => $c->where('type', $type))
            ->when($q !== '', function (Collection $c) use ($q) {
                $needle = mb_strtolower($q);
                return $c->filter(function ($it) use ($needle) {
                    $hay = mb_strtolower(($it['title'] ?? '') . ' ' . ($it['subtitle'] ?? ''));
                    return mb_strpos($hay, $needle) !== false;
                });
            })
            ->when($fromDate, fn($c) => $c->filter(fn($it) => strtotime((string)$it['created_at']) >= strtotime($fromDate . ' 00:00:00')))
            ->when($toDate,   fn($c) => $c->filter(fn($it) => strtotime((string)$it['created_at']) <= strtotime($toDate . ' 23:59:59')))
            ->sortByDesc(fn($x) => strtotime((string)$x['created_at']))
            ->values();

        // Pagination manuelle
        $total  = $items->count();
        $last   = (int) ceil(max($total, 1) / $perPage);
        $page   = min($page, max($last, 1));
        $offset = ($page - 1) * $perPage;
        $slice  = $items->slice($offset, $perPage)->values();

        return response()->json([
            'data' => $slice,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $last,
                'scope'        => $isPrivileged ? 'mixed' : 'personal_only',
            ],
        ]);
    }

    private function mapPersonalActivities(
        int $userId,
        $fromDate,
        $toDate,
        string $type,
        string $q,
        int $cap
    ): \Illuminate\Support\Collection {
        $qBuilder = Activity::query()
            ->where('recipient_id', $userId)
            ->orderByDesc('id');

        if ($fromDate) $qBuilder->whereDate('created_at', '>=', $fromDate);
        if ($toDate)   $qBuilder->whereDate('created_at', '<=', $toDate);
        if ($type !== '') $qBuilder->where('type', $type);
        if ($q !== '') {
            $like = '%' . $q . '%';
            $qBuilder->where(function ($qq) use ($like) {
                $qq->where('title', 'like', $like)
                   ->orWhere('subtitle', 'like', $like);
            });
        }

        // ⚠️ On NE PASSE PLUS de "select([...])" pour éviter l’erreur de colonne manquante
        $rows = $qBuilder->limit($cap)->get();

        return collect($rows)->map(function ($r) {
            // Si ta table n’a pas link/actor_id/target_id, on retombe à null
            $link   = $r->link     ?? $r->url      ?? null;
            $actor  = $r->actor_id ?? null;
            $target = $r->target_id ?? null;

            return [
                'id'           => 'notif-' . $r->id,
                'type'         => $r->type ?: 'default',
                'title'        => (string)($r->title ?? 'Notification'),
                'subtitle'     => (string)($r->subtitle ?? ''),
                'created_at'   => $r->created_at,
                'article_slug' => null,
                'comment_id'   => null,
                'actor_id'     => $actor,
                'target_id'    => $target,
                'url'          => $link,
            ];
        });
    }

    private function mapGlobalFeed(
        int $userId,
        bool $asTarget,
        $fromDate,
        $toDate,
        bool $isAdmin
    ): Collection {
        $items = collect();

        // Rôles (acteur/cible selon asTarget)
        $roleQ = UserRole::query()
            ->with(['user:id,first_name,last_name,username', 'role:id,name', 'assignedBy:id,first_name,last_name,username'])
            ->when($asTarget, fn($q) => $q->where('user_id', $userId), fn($q) => $q->where('assigned_by', $userId))
            ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(assigned_at, created_at)'), '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->whereDate(DB::raw('COALESCE(assigned_at, created_at)'), '<=', $toDate))
            ->orderByDesc(DB::raw('COALESCE(assigned_at, created_at)'))
            ->limit(500)
            ->get();

        foreach ($roleQ as $ur) {
            $by       = $this->displayName($ur->assignedBy);
            $to       = $this->displayName($ur->user);
            $roleName = $ur->role?->name ?? 'Inconnu';

            $items->push([
                'id'         => 'role-' . $ur->id,
                'type'       => 'role_assigned',
                'title'      => $asTarget
                    ? sprintf('%s t’a attribué le rôle « %s »', $by, $roleName)
                    : sprintf('%s a attribué le rôle « %s » à %s', $by, $roleName, $to),
                'subtitle'   => '',
                'created_at' => $ur->assigned_at ?? $ur->created_at,
                'actor_id'   => $ur->assigned_by,
                'target_id'  => $ur->user_id,
            ]);
        }

        // Articles créés par ce user
        $arts = Article::query()
            ->select('id', 'title', 'slug', 'created_at')
            ->where('created_by', $userId)
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        foreach ($arts as $a) {
            $items->push([
                'id'           => 'article-' . $a->id,
                'type'         => 'article_created',
                'title'        => sprintf('Tu as créé l’article « %s »', $a->title ?: 'Sans titre'),
                'subtitle'     => '',
                'created_at'   => $a->created_at,
                'article_slug' => $a->slug,
                'actor_id'     => $userId,
                'target_id'    => null,
            ]);
        }

        // Commentaires approuvés par ce user (modération)
        $coms = Comment::query()
            ->with(['article:id,title,slug'])
            ->where('moderated_by', $userId)
            ->where('status', 'approved')
            ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(moderated_at, updated_at, created_at)'), '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->whereDate(DB::raw('COALESCE(moderated_at, updated_at, created_at)'), '<=', $toDate))
            ->orderByDesc('moderated_at')
            ->limit(500)
            ->get();

        foreach ($coms as $c) {
            $items->push([
                'id'           => 'comment-approve-' . $c->id,
                'type'         => 'comment_approved',
                'title'        => sprintf('Tu as approuvé un commentaire sur « %s »', $c->article?->title ?: 'Article'),
                'subtitle'     => $this->shorten($c->content ?? '', 120),
                'created_at'   => $c->moderated_at ?? $c->updated_at ?? $c->created_at,
                'article_slug' => $c->article?->slug,
                'comment_id'   => $c->id,
                'actor_id'     => $userId,
                'target_id'    => null,
            ]);
        }

        // 🔔 Messages de contact "nouveaux" (flux global réservé aux ADMINS)
        if ($isAdmin) {
            $contactMessages = ContactMessage::query()
                ->select('id', 'name', 'email', 'subject', 'message', 'status', 'created_at')
                ->where('status', 'new') // uniquement les nouveaux
                ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
                ->when($toDate,   fn($q) => $q->whereDate('created_at', '<=', $toDate))
                ->orderByDesc('created_at')
                ->limit(300)
                ->get();

            foreach ($contactMessages as $m) {
                $displayName = trim($m->name ?: $m->email ?: 'Contact');

                $items->push([
                    'id'           => 'contact-' . $m->id,
                    'type'         => 'contact_message_new',
                    'title'        => sprintf('Nouveau message de %s', $displayName),
                    'subtitle'     => $this->shorten($m->message ?? '', 120),
                    'created_at'   => $m->created_at,
                    'article_slug' => null,
                    'comment_id'   => null,
                    'actor_id'     => null,
                    'target_id'    => null,
                    // 🔗 pour que le clic dans la notif amène directement à ta boîte de réception
                    'url'          => '/messageries',
                ]);
            }
        }

        // Permissions (audits) uniquement si tables présentes
        foreach ($this->fetchPermissionEvents(actorId: $userId) as $e) {
            $items->push($e);
        }

        return $items;
    }

    private function fetchPermissionEvents(?int $actorId = null, ?int $userAny = null): array
    {
        $out = [];

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
        $to    = $this->toBool($e->to_value) ? 'Activé' : 'Désactivé';
        $perm  = $e->permission_key ?: '—';

        return [
            'id'         => 'perm-' . $e->id,
            'type'       => 'permission_changed',
            'title'      => sprintf("%s a modifié les permissions du rôle « %s »", $actor, $role),
            'subtitle'   => sprintf('Permission: %s → %s', $perm, $to),
            'created_at' => $e->created_at,
            'actor_id'   => $e->actor_id ?? null,
            'target_id'  => null,
        ];
    }

    private function displayName($user): string
    {
        if (!$user) {
            return 'Quelqu’un';
        }

        $full = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $full !== '' ? $full : ($user->username ?? 'Quelqu’un');
    }

    private function shorten(string $text, int $limit = 120): string
    {
        $t = trim(strip_tags($text));

        return mb_strlen($t) > $limit
            ? (mb_substr($t, 0, $limit - 1) . '…')
            : $t;
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int) $value) === 1;

        $v = mb_strtolower((string) $value);

        return in_array($v, ['1', 'true', 'vrai', 'yes', 'oui'], true);
    }
}
