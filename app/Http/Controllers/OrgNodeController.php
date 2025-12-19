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
     * ✅ sortie gardée filtrée Admin
     */
    public function indexAdminUsers(Request $request)
    {
        $me = $request->user();
        if (!$me) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // 🔒 Si tu veux enlever tout contrôle ici aussi, supprime la ligne suivante.
        abort_unless($this->isAdmin($me), 403, 'Forbidden');

        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->whereHas('roles', function ($r) {
                $r->where('name', 'Admin')
                  ->orWhere('is_admin', 1);
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

        $q = OrgNode::query()
            ->with([
                'user:id,first_name,last_name,email,avatar_url',
                'parent:id,title,parent_id',
            ])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($all) {
            $items = $q->get()->map(fn($n) => $this->formatOrgNode($n));
            return response()->json(['data' => $items]);
        }

        $paginated = $q->paginate($perPage);
        $paginated->getCollection()->transform(fn($n) => $this->formatOrgNode($n));
        return response()->json($paginated);
    }

    /**
     * GET /api/orgnodes/slides
     */
    public function slides(Request $request)
    {
        $active = (int) $request->query('active', 1);

        $q = OrgNode::query()
            ->with(['user:id,first_name,last_name,email,avatar_url', 'parent:id,title,parent_id'])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($active) $q->where('is_active', 1);

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
                'is_active' => (bool)$n->is_active,
                'parent_id' => $n->parent_id,
                'user' => $n->user ? [
                    'id'         => $n->user->id,
                    'first_name' => $n->user->first_name,
                    'last_name'  => $n->user->last_name,
                    'email'      => $n->user->email,
                    'phone'        => $n->user->phone,
                    'tenant_id'        => $n->user->tenant_id,
                    'avatar_url' => $n->user->avatar_url,
                ] : null,
                'avatar_path' => $n->avatar_path,
            ];
        });

        return response()->json(['data' => $items]);
    }

    /**
     * GET /api/orgnodes/{orgnode}
     */
    public function show(OrgNode $orgnode)
    {
        $orgnode->load([
            'user:id,first_name,last_name,email,avatar_url',
            'parent:id,title,parent_id',
        ]);

        return response()->json([
            'data' => $this->formatOrgNode($orgnode),
        ]);
    }

    /**
     * POST /api/orgnodes
     * ✅ On ne change pas user_id envoyé.
     * ✅ Si user_id absent => fallback sur user connecté (optionnel)
     */
    public function store(StoreOrgNodeRequest $request)
    {
        $me = $request->user();
        $data = $request->validated();

        // ✅ ne pas écraser ce que le client envoie
        // fallback seulement si absent/vidé
        if (!array_key_exists('user_id', $data) || empty($data['user_id'])) {
            $data['user_id'] = $me?->id;
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('orgnodes', 'public');
            $data['avatar_path'] = $path;
        }

        $node = OrgNode::create($data);

        $node->load([
            'user:id,first_name,last_name,email,avatar_url',
            'parent:id,title,parent_id',
        ]);

        return response()->json([
            'data' => $this->formatOrgNode($node),
        ], 201);
    }

    /**
     * PUT /api/orgnodes/{orgnode}
     * ✅ On ne change pas user_id envoyé.
     * ✅ Si user_id est présent mais vide => on ne le touche pas (évite null).
     */
    public function update(UpdateOrgNodeRequest $request, OrgNode $orgnode)
    {
        $data = $request->validated();

        // si user_id envoyé vide, ne pas écraser
        if (array_key_exists('user_id', $data) && empty($data['user_id'])) {
            unset($data['user_id']);
        }

        if ($request->hasFile('avatar')) {
            if (!empty($orgnode->avatar_path)) {
                try { Storage::disk('public')->delete($orgnode->avatar_path); } catch (\Throwable $e) {}
            }
            $path = $request->file('avatar')->store('orgnodes', 'public');
            $data['avatar_path'] = $path;
        }

        $orgnode->update($data);

        $orgnode->load([
            'user:id,first_name,last_name,email,avatar_url',
            'parent:id,title,parent_id',
        ]);

        return response()->json([
            'data' => $this->formatOrgNode($orgnode),
        ]);
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

    private function isAdmin($user): bool
    {
        if (!$user) return false;

        if (method_exists($user, 'roles')) {
            $hasAdmin = $user->roles()
                ->where(function ($r) {
                    $r->where('name', 'Admin')
                      ->orWhere('is_admin', 1);
                })
                ->exists();

            if ($hasAdmin) return true;
        }

        return (int)($user->is_admin ?? 0) === 1;
    }

    private function formatOrgNode(OrgNode $n): array
    {
        return [
            'id'         => $n->id,
            'user_id'    => $n->user_id,
            'parent_id'  => $n->parent_id,
            'title'      => $n->title,
            'department' => $n->department,
            'badge'      => $n->badge,
            'subtitle'   => $n->subtitle,
            'bio'        => $n->bio,
            'avatar_path'=> $n->avatar_path,
            'level'      => $n->level,
            'accent'     => $n->accent,
            'sort_order' => $n->sort_order,
            'pos_x'      => $n->pos_x,
            'pos_y'      => $n->pos_y,
            'is_active'  => (bool)$n->is_active,
            'created_at' => $n->created_at,
            'updated_at' => $n->updated_at,

            'user' => $n->relationLoaded('user') && $n->user ? [
                'id'         => $n->user->id,
                'first_name' => $n->user->first_name,
                'last_name'  => $n->user->last_name,
                'email'      => $n->user->email,
                'phone'        => $n->user->phone,
                'tenant_id'        => $n->user->tenant_id,
                'avatar_url' => $n->user->avatar_url,
            ] : null,

            'parent' => $n->relationLoaded('parent') && $n->parent ? [
                'id'        => $n->parent->id,
                'title'     => $n->parent->title,
                'parent_id' => $n->parent->parent_id,
            ] : null,
        ];
    }
}
