<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrgNodeRequest;
use App\Http\Requests\UpdateOrgNodeRequest;
use App\Models\OrgNode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrgNodeController extends Controller
{
    /**
     * GET /api/orgnodes/admin-users
     * Liste des users Admin uniquement (pour picker user_id)
     */
    
    public function indexAdminUsers(Request $request)
{
    $me = $request->user();
    if (!$me) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    // Optionnel : sécuriser l’accès à la liste (sans toucher au modèle)
    $isAllowed =
        $me->roles()->where('name', 'Admin')->exists()
        || $me->roles()->whereHas('permissions', fn($p) => $p->where('name', 'users.read'))->exists();

    abort_unless($isAllowed, 403, 'Forbidden');

    $q = trim((string) $request->query('q', ''));

    $users = User::query()
        ->where(function ($qq) {
            $qq->whereHas('roles', fn($r) => $r->where('name', 'Admin'))
               ->orWhereHas('roles.permissions', fn($p) => $p->where('name', 'users.read'));
        })
        ->when($q, function ($qq) use ($q) {
            $qq->where(function ($s) use ($q) {
                $s->where('first_name', 'like', "%{$q}%")
                  ->orWhere('last_name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        })
        ->select(['id','first_name','last_name','email'])
        ->orderBy('first_name')
        ->limit(500)
        ->get()
        ->map(function ($u) use ($me) {
            return [
                'id'    => $u->id,
                'name'  => trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                'email' => $u->email,
                'is_me' => (string) $u->id === (string) $me->id,
            ];
        });

    return response()->json(['data' => $users]);
}

    /**
     * GET /api/orgnodes
     */
    public function index(Request $request)
{
    $all = (int) $request->query('all', 0);
    $perPage = (int) $request->query('per_page', 12);

    $q = \App\Models\OrgNode::query()
        ->with([
            'user' => function ($u) {
                $u->select('id','first_name','last_name','email','avatar_url');
            },
            'parent' => function ($p) {
                $p->select('id','title','parent_id');
            },
        ])
        ->orderBy('sort_order');

    if ($all) {
        $items = $q->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'title' => $n->title,
                'department' => $n->department,
                'badge' => $n->badge,
                'subtitle' => $n->subtitle,
                'bio' => $n->bio,
                'level' => $n->level,
                'accent' => $n->accent,
                'sort_order' => $n->sort_order,
                'pos_x' => $n->pos_x,
                'pos_y' => $n->pos_y,
                'is_active' => (bool) $n->is_active,
                'parent_id' => $n->parent_id,
                'user_id' => $n->user_id,
                'avatar_path' => $n->avatar_path ?? null,

                // ✅ Infos user (sans colonne "name")
                'name'  => $n->user ? trim(($n->user->first_name ?? '').' '.($n->user->last_name ?? '')) : null,
                'email' => $n->user->email ?? null,
                'avatar_url' => $n->user->avatar_url ?? null,
            ];
        });

        return response()->json(['data' => $items]);
    }

    $paginated = $q->paginate($perPage);

    // Transformer la pagination aussi
    $paginated->getCollection()->transform(function ($n) {
        $n->name = $n->user ? trim(($n->user->first_name ?? '').' '.($n->user->last_name ?? '')) : null;
        $n->email = $n->user->email ?? null;
        $n->avatar_url = $n->user->avatar_url ?? null;
        return $n;
    });

    return response()->json($paginated);
}


    /**
     * GET /api/orgnodes/{orgnode}
     */
    public function show(OrgNode $orgnode)
    {
        $orgnode->load(['user:id,name,email,avatar_url', 'parent:id,title']);
        return response()->json($orgnode);
    }

    /**
     * POST /api/orgnodes
     */
    public function store(StoreOrgNodeRequest $request)
    {
        $me = $request->user();
        $data = $request->validated();

        // ✅ assign user_id : seulement admin
        if ($this->isAdmin($me)) {
            $data['user_id'] = $this->ensureAdminUserId($data['user_id'] ?? null, $me->id);
        } else {
            $data['user_id'] = $me->id;
        }

        // upload avatar (optionnel)
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('orgnodes', 'public');
            $data['avatar_path'] = $path;
        }

        $node = OrgNode::create($data);
        $node->load(['user:id,name,email,avatar_url', 'parent:id,title']);

        return response()->json($node, 201);
    }

    /**
     * PATCH /api/orgnodes/{orgnode}
     */
    public function update(UpdateOrgNodeRequest $request, OrgNode $orgnode)
    {
        $me = $request->user();
        $data = $request->validated();

        // ✅ assign user_id : seulement admin
        if (array_key_exists('user_id', $data)) {
            if ($this->isAdmin($me)) {
                $data['user_id'] = $this->ensureAdminUserId($data['user_id'] ?? null, $orgnode->user_id);
            } else {
                unset($data['user_id']);
            }
        }

        // upload avatar (optionnel)
        if ($request->hasFile('avatar')) {
            if (!empty($orgnode->avatar_path)) {
                try {
                    Storage::disk('public')->delete($orgnode->avatar_path);
                } catch (\Throwable $e) {}
            }
            $path = $request->file('avatar')->store('orgnodes', 'public');
            $data['avatar_path'] = $path;
        }

        $orgnode->update($data);
        $orgnode->load(['user:id,name,email,avatar_url', 'parent:id,title']);

        return response()->json($orgnode);
    }

    /**
     * DELETE /api/orgnodes/{orgnode}
     */
    public function destroy(OrgNode $orgnode)
    {
        try {
            if (!empty($orgnode->avatar_path)) {
                Storage::disk('public')->delete($orgnode->avatar_path);
            }
        } catch (\Throwable $e) {}

        $orgnode->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * ✅ UNE SEULE méthode isAdmin (sinon erreur redeclare)
     */
    private function isAdmin($user): bool
{
    if (!$user) return false;

    // Spatie roles
    if (method_exists($user, 'hasRole') && $user->hasRole('Admin')) return true;

    // Spatie permissions
    if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('orgnodes.assign_user')) return true;

    // ✅ fallback Eloquent attribute
    return (int)($user->is_admin ?? 0) === 1;
}


    /**
     * Sécurise le user_id : doit être admin, sinon fallback
     */
    private function ensureAdminUserId($candidateId, $fallbackId)
    {
        $candidateId = $candidateId ? (int) $candidateId : null;

        if (!$candidateId) return (int) $fallbackId;

        $q = User::query()->where('id', $candidateId);

        if (method_exists($q->getModel(), 'roles')) {
            $q->whereHas('roles', fn ($r) => $r->where('name', 'Admin'));
        } else {
            $q->where('is_admin', 1);
        }

        return $q->exists() ? $candidateId : (int) $fallbackId;
    }
}
