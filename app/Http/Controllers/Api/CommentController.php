<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    /** Détermine si l'utilisateur est modérateur (voir rôles/permissions) */
    private function userIsModerator($user): bool
    {
        if (!$user) return false;

        $keys = [
            'commentspermissions.moderator','comments.moderator','comments.moderate',
            'moderate comments','moderator','moderateur','permissions.moderator',
            'manager','admin','administrator','super admin','superadmin','super-administrator',
            'approuve comments','approuver comments','approuver commentaires','gerer'
        ];

        // 1) via relation Eloquent (si dispo)
        try {
            if (method_exists($user, 'permissions')) {
                $perms = collect($user->permissions() ?? []);
                $haystack = $perms
                    ->map(fn($p) => [
                        mb_strtolower((string) data_get($p, 'name', '')),
                        mb_strtolower((string) data_get($p, 'slug', '')),
                    ])->flatten()->filter()->unique();

                foreach ($keys as $k) {
                    if ($haystack->contains(mb_strtolower($k))) return true;
                }
            }
        } catch (\Throwable $e) {}

        // 2) via tables pivot
        try {
            $hasSlug = Schema::hasColumn('permissions', 'slug');

            return DB::table('permissions as p')
                ->join('role_permissions as rp', 'rp.permission_id', '=', 'p.id')
                ->join('roles as r', 'r.id', '=', 'rp.role_id')
                ->join('user_roles as ur', 'ur.role_id', '=', 'r.id')
                ->where('ur.user_id', $user->id)
                ->where(function ($q) use ($keys, $hasSlug) {
                    $q->whereIn('p.name', $keys);
                    if ($hasSlug) $q->orWhereIn('p.slug', $keys);
                })
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Règle stricte d'édition de contenu pour l’auteur */
    private function authorCanEditContent(?\App\Models\User $user, Comment $comment): bool
    {
        if (!$user) return false;
        if ((int)$comment->user_id !== (int)$user->id) return false;

        // ✳️ Interdiction si approuvé / rejeté / spam
        if ($comment->isApproved() || $comment->isRejected() || $comment->isSpam()) return false;

        // ✳️ Interdiction s'il y a au moins une réponse
        $hasReplies = (int)$comment->reply_count > 0
            || Comment::query()->where('parent_id', $comment->id)->exists();
        if ($hasReplies) return false;

        return true;
    }

    /**
     * GET /api/comments
     * Filtres: status, article_id, parent_id ("null" pour racine), user_id, featured, tenant_id
     */
  public function index(Request $request): JsonResponse
{
    $query = Comment::query()
        ->with([
            'user:id,username,email,first_name,last_name,avatar_url,updated_at',
            'parent:id',
            'article:id,title',
        ])
        ->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', (int) $request->get('tenant_id')))
        ->when($request->filled('article_id'), fn ($q) => $q->where('article_id', (int) $request->get('article_id')))
        ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->get('user_id')))
        ->when($request->filled('featured'), fn ($q) => $q->where('is_featured', filter_var($request->get('featured'), FILTER_VALIDATE_BOOL)))
        ->when($request->filled('parent_id'), fn ($q) => $q->where('parent_id', $request->get('parent_id')));

    if ($request->get('parent_id') === 'null') {
        $query->whereNull('parent_id');
    }

    $user = $request->user();
    $isModerator = $this->userIsModerator($user);

    if (!$request->filled('status')) {
        if (!$isModerator) {
            $query->where(function ($q) use ($user) {
                $q->where('status', 'approved');
                if ($user) {
                    $q->orWhere(fn ($qq) => $qq->where('status', 'pending')->where('user_id', $user->id));
                }
            });
        }
    } else {
        $query->whereIn('status', (array) $request->get('status'));
    }

    if ($request->boolean('with_trashed')) $query->withTrashed();
    if ($request->boolean('only_trashed')) $query->onlyTrashed();

    // -------- TRI ----------
    $sort = strtolower((string) $request->get('sort', 'newest'));
    $featuredFirst = $request->boolean('featured_first', true);
    if (!in_array($sort, ['newest', 'oldest'], true)) $sort = 'newest';

    if ($featuredFirst) {
        $query->orderByDesc('is_featured');
    }
    $query->orderBy('created_at', $sort === 'newest' ? 'desc' : 'asc');

    $perPage = min(max((int) $request->get('per_page', 20), 1), 100);
    $paginated = $query->paginate($perPage);

    return response()->json($paginated->toArray());
}

    /**
     * GET /api/comments/{article}
     * Liste paginée des commentaires RACINE d’un article
     */
    public function show(Request $request, int $article): JsonResponse
{
    $query = Comment::query()
        ->with([
            'user:id,username,email,first_name,last_name,avatar_url,updated_at',
            'parent:id',
            'article:id,title',
        ])
        ->where('article_id', $article);

    // Seulement racine par défaut
    if ($request->get('parent_id') === 'null' || !$request->filled('parent_id')) {
        $query->whereNull('parent_id');
    } else {
        $query->where('parent_id', $request->get('parent_id'));
    }

    $user = $request->user();
    $isModerator = $this->userIsModerator($user);

    if (!$request->filled('status')) {
        if (!$isModerator) {
            $query->where(function ($q) use ($user) {
                $q->where('status', 'approved');
                if ($user) {
                    $q->orWhere(fn ($qq) => $qq->where('status', 'pending')->where('user_id', $user->id));
                }
            });
        }
    } else {
        $query->whereIn('status', (array) $request->get('status'));
    }

    if ($request->boolean('with_trashed')) $query->withTrashed();
    if ($request->boolean('only_trashed')) $query->onlyTrashed();

    // -------- TRI ----------
    $sort = strtolower((string) $request->get('sort', 'newest'));
    $featuredFirst = $request->boolean('featured_first', true);
    if (!in_array($sort, ['newest', 'oldest'], true)) $sort = 'newest';

    if ($featuredFirst) {
        $query->orderByDesc('is_featured');
    }
    $query->orderBy('created_at', $sort === 'newest' ? 'desc' : 'asc');

    $perPage  = min(max((int) $request->get('per_page', 20), 1), 100);
    $paginated = $query->paginate($perPage);

    return response()->json($paginated->toArray());
}

    /**
     * GET /api/comments/{comment}/replies
     */
  public function replies(Request $request, Comment $comment): JsonResponse
{
    $query = Comment::query()
        ->with([
            'user:id,username,email,first_name,last_name,avatar_url,updated_at',
            'parent:id',
            'article:id,title',
        ])
        ->where('parent_id', $comment->id);

    $user = $request->user();
    $isModerator = $this->userIsModerator($user);

    if (!$request->filled('status')) {
        if (!$isModerator) {
            $query->where(function ($q) use ($user) {
                $q->where('status', 'approved');
                if ($user) {
                    $q->orWhere(fn ($qq) => $qq->where('status', 'pending')->where('user_id', $user->id));
                }
            });
        }
    } else {
        $query->whereIn('status', (array) $request->get('status'));
    }

    if ($request->boolean('with_trashed')) $query->withTrashed();
    if ($request->boolean('only_trashed')) $query->onlyTrashed();

    // -------- TRI ----------
    $sort = strtolower((string) $request->get('sort', 'newest'));           // ← plus récent par défaut
    $featuredFirst = $request->boolean('featured_first', false);            // ← les réponses n’ont pas besoin d’être "featured"
    if (!in_array($sort, ['newest', 'oldest'], true)) $sort = 'newest';

    if ($featuredFirst) {
        $query->orderByDesc('is_featured');
    }
    $query->orderBy('created_at', $sort === 'newest' ? 'desc' : 'asc');

    $perPage = min(max((int) $request->get('per_page', 3), 1), 50);
    $paginated = $query->paginate($perPage);

    return response()->json($paginated->toArray());
}

    /** GET /api/comment/{comment} */
    public function showOne(Comment $comment): JsonResponse
    {
        return response()->json(
            $comment->load([
                'user:id,username,email,first_name,last_name,avatar_url,updated_at',
                'parent:id',
                'article:id,title',
                'replies',
            ])->toArray()
        );
    }

    /** POST /api/comments */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        $isModerator = $this->userIsModerator($user);

        $data = $request->validate([
            'tenant_id'     => ['nullable', 'integer'],
            'article_id'    => ['required', 'integer', Rule::exists('articles', 'id')],
            'parent_id'     => ['nullable', 'integer', Rule::exists('comments', 'id')],
            'content'       => ['required', 'string', 'min:1', 'max:5000'],
            'guest_name'    => ['nullable', 'string', 'max:100'],
            'guest_website' => ['nullable', 'url', 'max:255'],
            'status'        => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'spam'])],
            'meta'          => ['nullable', 'array'],
        ]);

        $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        $data['guest_name']  = $user->username ?: ($fullName ?: null);
        $data['guest_email'] = $user->email ?? null;
        $data['user_id']     = $user->id;

        if (!isset($data['tenant_id']) && property_exists($user, 'tenant_id')) {
            $data['tenant_id'] = $user->tenant_id;
        }

        $parent = null;
        if (!empty($data['parent_id'])) {
            $parent = Comment::query()->findOrFail($data['parent_id']);
            if (!$parent->isApproved()) {
                return response()->json(['message' => 'Impossible de répondre à un commentaire non approuvé.'], 422);
            }
            if ($parent->getLevel() >= 3) {
                return response()->json(['message' => 'Profondeur maximale de fil atteinte.'], 422);
            }
            $data['article_id'] = $parent->article_id;
        }

        // statut par défaut
        if (empty($data['status'])) {
            $data['status'] = $isModerator ? 'approved' : 'pending';
        } elseif (!$isModerator) {
            $data['status'] = 'pending';
        }

        $comment = DB::transaction(function () use ($data, $parent) {
            $comment = Comment::create($data);

            if ($parent) $parent->incrementReplyCount();

            if ($comment->isApproved()) {
                $comment->loadMissing('article');
                if ($comment->article && method_exists($comment->article, 'incrementCommentCount')) {
                    $comment->article->incrementCommentCount();
                }
            }

            return $comment;
        });

        return response()->json(
            $comment->load([
                'user:id,username,email,first_name,last_name,avatar_url,updated_at',
                'parent:id',
                'article:id,title',
            ])->toArray(),
            201
        );
    }

    /** PATCH /api/comments/{comment} */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        $isModerator = $this->userIsModerator($user);
        $isOwner     = (int)$comment->user_id === (int)$user->id;

        $data = $request->validate([
            'content'           => ['sometimes', 'string', 'min:1', 'max:5000'],
            'is_featured'       => ['sometimes', 'boolean'],
            'meta'              => ['sometimes', 'array'],
            'status'            => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'spam'])],
            'moderation_notes'  => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        // 1) ÉDITION DE CONTENU — uniquement l’auteur, et règle stricte (pas approved/rejected/spam, pas de réponses)
        $contentPayload = array_intersect_key($data, array_flip(['content','meta']));
        if (!empty($contentPayload)) {
            if (!$this->authorCanEditContent($user, $comment)) {
                return response()->json(['message' => 'Non autorisé à modifier ce commentaire.'], 403);
            }
        }

        // 2) CHANGEMENTS DE STATUT & FEATURE — modérateur uniquement
        if (isset($data['status']) || isset($data['is_featured'])) {
            if (!$isModerator) {
                // un auteur non modérateur ne peut pas changer le statut/feature
                unset($data['status'], $data['is_featured']);
            }
        }

        // appliquer status si modérateur
        if ($isModerator && isset($data['status'])) {
            $oldApproved = $comment->isApproved();
            $to = $data['status'];

            if ($to === 'approved' && !$comment->isApproved()) {
                $comment->approve(Auth::user(), $data['moderation_notes'] ?? null);
            } elseif ($to === 'rejected' && !$comment->isRejected()) {
                $comment->reject(Auth::user(), $data['moderation_notes'] ?? 'Rejeté.');
            } elseif ($to === 'spam' && !$comment->isSpam()) {
                $comment->markAsSpam(Auth::user(), $data['moderation_notes'] ?? null);
            }
            unset($data['status']);

            $comment->refresh()->loadMissing('article');
            $newApproved = $comment->isApproved();
            if ($oldApproved && !$newApproved && $comment->article && method_exists($comment->article, 'decrementCommentCount')) {
                $comment->article->decrementCommentCount();
            }
        }

        // feature si modérateur
        if ($isModerator && array_key_exists('is_featured', $data)) {
            $comment->is_featured = (bool)$data['is_featured'];
            unset($data['is_featured']);
        }

        // appliquer édition contenu si autorisée
        if (!empty($contentPayload)) {
            $comment->fill($contentPayload);
        }

        if ($comment->isDirty()) {
            $comment->save();
        }

        return response()->json(
            $comment->fresh()->load([
                'user:id,username,email,first_name,last_name,avatar_url,updated_at',
                'parent:id',
                'article:id,title',
            ])->toArray()
        );
    }

    /** DELETE /api/comments/{comment} */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        $isModerator = $this->userIsModerator($user);

        // Autorisation : auteur OU modérateur
        if (!$isModerator && $comment->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé à supprimer ce commentaire.'], 403);
        }

        // Si non modérateur → on bloque s'il y a des réponses
        $hasReplies = (int) $comment->reply_count > 0
            || Comment::query()->where('parent_id', $comment->id)->exists();

        if ($hasReplies && !$isModerator) {
            return response()->json([
                'message' => "Impossible de supprimer ce commentaire : des réponses existent."
            ], 422);
        }

        DB::transaction(function () use ($comment, $isModerator) {
            $comment->loadMissing('article');
            $article  = $comment->article;
            $isReply  = $comment->isReply();
            $parentId = $comment->parent_id;

            $idsToDelete = [$comment->id];
            $approvedDeletedCount = $comment->isApproved() ? 1 : 0;

            if ($isModerator) {
                // delete sous-arbre
                $frontier = [$comment->id];
                while (!empty($frontier)) {
                    $children = Comment::query()
                        ->whereIn('parent_id', $frontier)
                        ->get(['id','status','parent_id']);

                    $frontier = [];
                    foreach ($children as $child) {
                        $idsToDelete[] = $child->id;
                        if (method_exists($child, 'isApproved')) {
                            if ($child->isApproved()) $approvedDeletedCount++;
                        } else {
                            if ($child->status === 'approved') $approvedDeletedCount++;
                        }
                        $frontier[] = $child->id;
                    }
                }
            }

            Comment::query()->whereIn('id', $idsToDelete)->delete();

            if ($isReply && $parentId) {
                Comment::query()->find($parentId)?->decrementReplyCount();
            }

            if ($article && $approvedDeletedCount > 0) {
                if (method_exists($article, 'decrementCommentCount')) {
                    for ($i = 0; $i < $approvedDeletedCount; $i++) {
                        $article->decrementCommentCount();
                    }
                } else {
                    $article->decrement('comment_count', $approvedDeletedCount);
                }
            }
        });

        return response()->json([], 204);
    }

    /** POST /api/comments/{id}/restore */
    public function restore(string $id): JsonResponse
    {
        $comment = Comment::withTrashed()->findOrFail($id);

        $user = Auth::user();
        $isModerator = $this->userIsModerator($user);
        if (!$isModerator && $comment->user_id !== ($user?->id)) {
            return response()->json(['message' => 'Non autorisé à restaurer ce commentaire.'], 403);
        }

        DB::transaction(function () use ($comment) {
            $comment->restore();

            $comment->loadMissing('article');
            if ($comment->isApproved() && $comment->article && method_exists($comment->article, 'incrementCommentCount')) {
                $comment->article->incrementCommentCount();
            }

            if ($comment->parent_id) {
                Comment::query()->find($comment->parent_id)?->incrementReplyCount();
            }
        });

        return response()->json(
            $comment->fresh()->load([
                'user:id,username,email,first_name,last_name,avatar_url,updated_at',
                'parent:id',
                'article:id,title',
            ])->toArray()
        );
    }

    /** POST /api/comments/{comment}/approve */
    public function approve(Request $request, Comment $comment): JsonResponse
    {
        if (!$this->userIsModerator($request->user())) {
            return response()->json(['message' => 'Action réservée aux modérateurs.'], 403);
        }

        $notes = $request->string('notes')->toString() ?: null;
        $wasApproved = $comment->isApproved();

        $comment->approve(Auth::user(), $notes);

        if (!$wasApproved && $comment->isApproved()) {
            $comment->loadMissing('article');
            if ($comment->article && method_exists($comment->article, 'incrementCommentCount')) {
                $comment->article->incrementCommentCount();
            }
        }

        return response()->json($comment->fresh()->toArray());
    }

    /** POST /api/comments/{comment}/reject */
    public function reject(Request $request, Comment $comment): JsonResponse
    {
        if (!$this->userIsModerator($request->user())) {
            return response()->json(['message' => 'Action réservée aux modérateurs.'], 403);
        }

        $notes = $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
        ])['notes'];

        $wasApproved = $comment->isApproved();
        $comment->reject(Auth::user(), $notes);

        if ($wasApproved && !$comment->isApproved()) {
            $comment->loadMissing('article');
            if ($comment->article && method_exists($comment->article, 'decrementCommentCount')) {
                $comment->article->decrementCommentCount();
            }
        }

        return response()->json($comment->fresh()->toArray());
    }

    /** POST /api/comments/{comment}/spam */
    public function spam(Request $request, Comment $comment): JsonResponse
    {
        if (!$this->userIsModerator($request->user())) {
            return response()->json(['message' => 'Action réservée aux modérateurs.'], 403);
        }

        $notes = $request->string('notes')->toString() ?: null;

        $wasApproved = $comment->isApproved();
        $comment->markAsSpam(Auth::user(), $notes);

        if ($wasApproved && !$comment->isApproved()) {
            $comment->loadMissing('article');
            if ($comment->article && method_exists($comment->article, 'decrementCommentCount')) {
                $comment->article->decrementCommentCount();
            }
        }

        return response()->json($comment->fresh()->toArray());
    }

    /** POST /api/comments/{comment}/reply */
    public function reply(Request $request, Comment $comment): JsonResponse
    {
        if (!$comment->canBeRepliedTo()) {
            return response()->json(['message' => 'Ce commentaire ne peut plus recevoir de réponses.'], 422);
        }

        $request->merge([
            'parent_id'  => $comment->id,
            'article_id' => $comment->article_id,
        ]);

        return $this->store($request);
    }

    /** POST /api/comments/{comment}/like */
    public function like(Request $request, Comment $comment): JsonResponse
    {
        $action = $request->validate([
            'action' => ['required', Rule::in(['like', 'unlike'])],
        ])['action'];

        if ($action === 'like') {
            $comment->incrementLikeCount();
        } else {
            $comment->decrementLikeCount();
        }

        $fresh = $comment->fresh();

        return response()->json([
            'like_count'    => $fresh->like_count,
            'dislike_count' => $fresh->dislike_count,
            'vote_ratio'    => $fresh->getVoteRatio(),
        ]);
    }

    /** POST /api/comments/{comment}/dislike */
    public function dislike(Request $request, Comment $comment): JsonResponse
    {
        $action = $request->validate([
            'action' => ['required', Rule::in(['dislike', 'undislike'])],
        ])['action'];

        if ($action === 'dislike') {
            $comment->incrementDislikeCount();
        } else {
            $comment->decrementDislikeCount();
        }

        $fresh = $comment->fresh();

        return response()->json([
            'like_count'    => $fresh->like_count,
            'dislike_count' => $fresh->dislike_count,
            'vote_ratio'    => $fresh->getVoteRatio(),
        ]);
    }

    /** POST /api/comments/{comment}/feature */
    public function feature(Request $request, Comment $comment): JsonResponse
    {
        if (!$this->userIsModerator($request->user())) {
            return response()->json(['message' => 'Action réservée aux modérateurs.'], 403);
        }

        $featured = $request->validate([
            'featured' => ['required', 'boolean'],
        ])['featured'];

        $comment->is_featured = $featured;
        $comment->save();

        return response()->json($comment->fresh()->toArray());
    }
}
