<?php

namespace App\Http\Controllers\Api;

use App\Enums\ArticleVisibility;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ArticleController extends Controller
{
    /* =========================================================
     * INDEX
     * ========================================================= */
    public function index(Request $request): JsonResponse
    {
        // --- Validation souple (IDs CSV ou tableaux) ---
        $validated = $request->validate([
            'page'           => 'nullable|integer|min:1',
            'per_page'       => 'nullable|integer|min:1|max:100',
            'search'         => 'nullable|string|max:255',

            // Filtres par IDs ou noms/slugs (CSV)
            'category_ids'   => 'nullable|string',
            'categories'     => 'nullable|string',
            'category_slugs' => 'nullable|string',

            'tag_ids'        => 'nullable|string',
            'tags'           => 'nullable|string',
            'tag_slugs'      => 'nullable|string',

            'author_id'      => 'nullable|integer|exists:users,id',
            'author_ids'     => 'nullable|string',

            'status'         => 'nullable|string|in:draft,pending,published,archived',
            'featured'       => 'nullable|boolean',
            'sticky'         => 'nullable|boolean',

            'date_from'      => 'nullable|date',
            'date_to'        => 'nullable|date',

            'rating_min'     => 'nullable|numeric|min:0|max:5',
            'rating_max'     => 'nullable|numeric|min:0|max:5',

            // Tri (multi-colonnes)
            'sort'           => 'nullable|string', // ex: "published_at,desc;share_count,desc"
            'sort_by'        => 'nullable|string|in:created_at,updated_at,published_at,title,view_count,rating_average,share_count,comment_count',
            'sort_direction' => 'nullable|string|in:asc,desc',

            // Sélection & relations
            'include'        => 'nullable|string',
            'fields'         => 'nullable|string',

            // Facettes
            'include_facets' => 'nullable|boolean',
            'facet_fields'   => 'nullable|string',

            // Visibilité & masquage
            'visibility'     => 'nullable|string|in:public,private,password_protected',
            'hide_locked'    => 'nullable|boolean',
        ]);

        // --- Helpers ---
        $csv = fn($v) => collect(is_array($v) ? $v : explode(',', (string)$v))
            ->map(fn($x) => trim((string)$x))
            ->filter()
            ->values();

        $toInts = fn($c) => $c->map(fn($x) => (int)$x)->filter()->values();

        $articleTable = (new Article())->getTable();

        $allowedColumns = [
            'id','tenant_id','title','slug','excerpt','content',
            'featured_image','featured_image_alt','meta','seo_data',
            'status','visibility','password',
            'published_at','scheduled_at','expires_at',
            'reading_time','word_count','view_count','comment_count',
            'rating_average','rating_count',
            'is_featured','is_sticky','allow_comments','allow_sharing','allow_rating',
            'author_name','author_bio','author_avatar','author_id',
            'created_by','updated_by','reviewed_by','reviewed_at','review_notes',
            'created_at','updated_at','deleted_at',
        ];

        // Sélection des colonnes
        $select = $allowedColumns;
        if (!empty($validated['fields'])) {
            $requested = $csv($validated['fields'])->all();
            $sel = array_values(array_intersect($allowedColumns, $requested));
            if ($sel) $select = $sel;
        }
        // retire share_count persisté (conflit withCount)
        $select = array_values(array_diff($select, ['share_count']));

        // Relations autorisées
        $relationsAllowed = [
            'categories','tags','media','comments','approvedComments','ratings','shares','history',
            'author','createdBy','updatedBy','reviewedBy',
        ];
        $includes = $relationsAllowed;
        if (!empty($validated['include'])) {
            $asked    = $csv($validated['include'])->all();
            $inc      = array_values(array_intersect($relationsAllowed, $asked));
            if ($inc) $includes = $inc;
        }

        // --- Base query ---
        $base = Article::query()
            ->from($articleTable)
            ->select($select)
            ->with($includes)
            ->withCount([
                'shares as share_count' => fn($q) => $q,
                'comments','approvedComments','ratings','history','media','tags','categories'
            ])
            ->withAvg('ratings', 'rating');

        // Texte libre
        if (!empty($validated['search'])) {
            $s = $validated['search'];
            $base->where(function ($q) use ($s, $articleTable) {
                $q->where($articleTable.'.title', 'like', "%{$s}%")
                  ->orWhere($articleTable.'.excerpt', 'like', "%{$s}%")
                  ->orWhere($articleTable.'.content', 'like', "%{$s}%");
            });
        }

        // Statut par défaut : publiés & non expirés
        if (!empty($validated['status'])) {
            $base->where($articleTable.'.status', $validated['status']);
        } else {
            $base->where($articleTable.'.status', 'published')
                 ->whereNotNull($articleTable.'.published_at')
                 ->where($articleTable.'.published_at', '<=', now())
                 ->where(function ($q) use ($articleTable) {
                     $q->whereNull($articleTable.'.expires_at')
                       ->orWhere($articleTable.'.expires_at', '>', now());
                 });
        }

        // Visibilité (optionnel)
        if (!empty($validated['visibility'])) {
            $base->where($articleTable.'.visibility', $validated['visibility']);
        }

        // Dates
        if (!empty($validated['date_from'])) {
            $base->where($articleTable.'.published_at','>=',$validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $base->where($articleTable.'.published_at','<=',$validated['date_to']);
        }

        // Rating
        if (isset($validated['rating_min'])) {
            $base->where($articleTable.'.rating_average','>=',(float)$validated['rating_min']);
        }
        if (isset($validated['rating_max'])) {
            $base->where($articleTable.'.rating_average','<=',(float)$validated['rating_max']);
        }

        // Flags
        if (array_key_exists('featured', $validated) && $request->boolean('featured')) {
            $base->where($articleTable.'.is_featured', true);
        }
        if (array_key_exists('sticky', $validated) && $request->boolean('sticky')) {
            $base->where($articleTable.'.is_sticky', true);
        }

        // Auteurs
        if (!empty($validated['author_id'])) {
            $base->where($articleTable.'.author_id', (int)$validated['author_id']);
        }
        if (!empty($validated['author_ids'])) {
            $authorIds = $toInts($csv($validated['author_ids']))->all();
            if ($authorIds) $base->whereIn($articleTable.'.author_id', $authorIds);
        }

        // Catégories
        if (!empty($validated['category_ids']) || !empty($validated['categories']) || !empty($validated['category_slugs'])) {
            $catIds   = $toInts($csv($validated['category_ids'] ?? ''))->all();
            $catNames = $csv($validated['categories'] ?? '')->all();
            $catSlugs = $csv($validated['category_slugs'] ?? '')->all();

            $base->whereHas('categories', function ($q) use ($catIds, $catNames, $catSlugs) {
                $q->where(function ($qq) use ($catIds, $catNames, $catSlugs) {
                    if ($catIds)   $qq->orWhereIn('categories.id', $catIds);
                    if ($catNames) $qq->orWhereIn('categories.name', $catNames);
                    if ($catSlugs) $qq->orWhereIn('categories.slug', $catSlugs);
                });
            });
        }

        // Tags
        if (!empty($validated['tag_ids']) || !empty($validated['tags']) || !empty($validated['tag_slugs'])) {
            $tagIds   = $toInts($csv($validated['tag_ids'] ?? ''))->all();
            $tagNames = $csv($validated['tags'] ?? '')->all();
            $tagSlugs = $csv($validated['tag_slugs'] ?? '')->all();

            $base->whereHas('tags', function ($q) use ($tagIds, $tagNames, $tagSlugs) {
                $q->where(function ($qq) use ($tagIds, $tagNames, $tagSlugs) {
                    if ($tagIds)   $qq->orWhereIn('tags.id', $tagIds);
                    if ($tagNames) $qq->orWhereIn('tags.name', $tagNames);
                    if ($tagSlugs) $qq->orWhereIn('tags.slug', $tagSlugs);
                });
            });
        }

        // Tri
        $sortable = ['created_at','updated_at','published_at','title','view_count','rating_average','share_count','comment_count'];
        if (!empty($validated['sort'])) {
            foreach ($csv($validated['sort']) as $spec) {
                [$k, $d] = array_pad(explode(',', $spec, 2), 2, 'asc');
                $k = trim($k);
                $d = strtolower(trim($d)) === 'desc' ? 'desc' : 'asc';
                if (!in_array($k, $sortable, true)) continue;
                if ($k === 'published_at') {
                    $base->orderBy($articleTable.'.is_sticky','desc')
                         ->orderBy($articleTable.'.is_featured','desc');
                }
                $base->orderBy($k === 'published_at' ? $articleTable.'.'.$k : $k, $d);
            }
        } else {
            $sortBy        = $validated['sort_by']        ?? 'published_at';
            $sortDirection = $validated['sort_direction'] ?? 'desc';
            if ($sortBy === 'published_at') {
                $base->orderBy($articleTable.'.is_sticky','desc')
                     ->orderBy($articleTable.'.is_featured','desc');
            }
            $base->orderBy(in_array($sortBy, ['published_at']) ? $articleTable.'.'.$sortBy : $sortBy, $sortDirection);
        }

        // Clone pour facettes (avant pagination)
        $forFacets = clone $base;

        // Pagination
        $perPage   = (int) ($validated['per_page'] ?? 15);
        $paginator = $base->paginate($perPage)->appends($request->query());

        // 🔁 Normalisation des métriques + verrouillage
        $hideLocked = $request->boolean('hide_locked');
        $paginator->setCollection(
            $paginator->getCollection()
                ->filter(function ($item) use ($hideLocked) {
                    if (!$hideLocked) return true;

                    $vis = $item->visibility instanceof \BackedEnum
                        ? $item->visibility->value
                        : (string)$item->visibility;

                    return !in_array($vis, [
                        ArticleVisibility::PRIVATE->value,
                        ArticleVisibility::PASSWORD_PROTECTED->value
                    ], true);
                })
                ->map(function ($item) {
                    // comment_count
                    if ($item->getAttribute('comment_count') === null) {
                        if ($item->getAttribute('approved_comments_count') !== null) {
                            $item->setAttribute('comment_count', (int) $item->getAttribute('approved_comments_count'));
                        } elseif ($item->getAttribute('comments_count') !== null) {
                            $item->setAttribute('comment_count', (int) $item->getAttribute('comments_count'));
                        }
                    }
                    // rating_average
                    if ($item->getAttribute('rating_average') === null && $item->getAttribute('ratings_avg_rating') !== null) {
                        $item->setAttribute('rating_average', (float) $item->getAttribute('ratings_avg_rating'));
                    }

                    // Indicateur de verrouillage pour l’index
                    $vis = $item->visibility instanceof \BackedEnum
                        ? $item->visibility->value
                        : (string)$item->visibility;

                    $isPrivate = ($vis === ArticleVisibility::PRIVATE->value);
                    $isPwd     = ($vis === ArticleVisibility::PASSWORD_PROTECTED->value);
                    $locked    = $isPrivate || $isPwd;

                    $item->setAttribute('locked', $locked);
                    $item->setAttribute('reason', $locked ? ($isPrivate ? 'private' : 'password_required') : null);

                    // Ne jamais exposer password ni content si verrouillé
                    if ($locked) {
                        $item->setAttribute('content', null);
                    }
                    $item->makeHidden(['password']);

                    return $item;
                })
        );

        // Facettes (robuste)
        $facets = null;
        if ($request->boolean('include_facets')) {
            try {
                $fields = $csv($validated['facet_fields'] ?? 'categories,tags,authors')->all();

                // ids de TOUT l'ensemble filtré (pas seulement la page)
                $filteredIds = (clone $forFacets)->select($articleTable.'.id')->pluck('id');

                $facets = [];

                if ($filteredIds->isNotEmpty()) {
                    if (in_array('categories', $fields, true) && Schema::hasTable('categories') && Schema::hasTable('article_categories')) {
                        $facets['categories'] = DB::table('article_categories')
                            ->whereIn('article_id', $filteredIds)
                            ->join('categories','categories.id','=','article_categories.category_id')
                            ->select('categories.id','categories.name', DB::raw('COUNT(*) as count'))
                            ->groupBy('categories.id','categories.name')
                            ->orderByDesc('count')
                            ->get();
                    }

                    if (in_array('tags', $fields, true) && Schema::hasTable('tags') && Schema::hasTable('article_tags')) {
                        $facets['tags'] = DB::table('article_tags')
                            ->whereIn('article_id', $filteredIds)
                            ->join('tags','tags.id','=','article_tags.tag_id')
                            ->select('tags.id','tags.name', DB::raw('COUNT(*) as count'))
                            ->groupBy('tags.id','tags.name')
                            ->orderByDesc('count')
                            ->get();
                    }

                    if (in_array('authors', $fields, true) && Schema::hasTable('users')) {
                        $facets['authors'] = DB::table($articleTable)
                            ->whereIn($articleTable.'.id', $filteredIds)
                            ->leftJoin('users','users.id','=',$articleTable.'.author_id')
                            ->select(
                                'users.id',
                                DB::raw("COALESCE(users.name, CONCAT('Auteur #', ".$articleTable.".author_id)) as name"),
                                DB::raw('COUNT(*) as count')
                            )
                            ->groupBy('users.id','users.name', $articleTable.'.author_id')
                            ->orderByDesc('count')
                            ->get();
                    }
                } else {
                    $facets = ['categories' => collect(), 'tags' => collect(), 'authors' => collect()];
                }
            } catch (\Throwable $e) {
                \Log::error('Facets error: '.$e->getMessage());
                $facets = null; // on n’échoue pas la route pour autant
            }
        }

        return response()->json([
            'data'  => $paginator->items(),
            'meta'  => [
                'current_page'        => $paginator->currentPage(),
                'per_page'            => $paginator->perPage(),
                'from'                => $paginator->firstItem(),
                'to'                  => $paginator->lastItem(),
                'total'               => $paginator->total(),
                'last_page'           => $paginator->lastPage(),
                'relations_included'  => implode(',', $includes),
                'filters'             => [
                    'search'      => $validated['search']      ?? null,
                    'status'      => $validated['status']      ?? 'published',
                    'visibility'  => $validated['visibility']  ?? null,
                    'date_from'   => $validated['date_from']   ?? null,
                    'date_to'     => $validated['date_to']     ?? null,
                    'rating_min'  => $validated['rating_min']  ?? null,
                    'rating_max'  => $validated['rating_max']  ?? null,
                    'featured'    => array_key_exists('featured', $validated) ? (bool)$validated['featured'] : null,
                    'sticky'      => array_key_exists('sticky', $validated)   ? (bool)$validated['sticky']   : null,
                    'hide_locked' => $validated['hide_locked'] ?? null,
                ],
                'sort'                => $validated['sort'] ?? null,
                'sort_by'             => $validated['sort_by'] ?? 'published_at',
                'sort_direction'      => $validated['sort_direction'] ?? 'desc',
                'facets'              => $facets,
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /* =========================================================
     * SHOW (lit aussi le header X-Article-Password)
     * ========================================================= */
    public function show(Request $request, string $idOrSlug): JsonResponse
    {
        // Log de l'utilisateur actuel
        $this->logCurrentUser($request);

        // Validation des paramètres
        $validated = $request->validate([
            'include'        => 'nullable|string',
            'fields'         => 'nullable|string',
            'status'         => 'nullable|string|in:draft,pending,published,archived',
            'password'       => 'nullable|string|max:255',
            'increment_view' => 'nullable|boolean',
        ]);

        // Helpers
        $csv = fn($v) => collect(is_array($v) ? $v : explode(',', (string) $v))
            ->map(fn($x) => trim((string) $x))
            ->filter()
            ->values();

        $articleTable = (new Article())->getTable();

        // Colonnes autorisées
        $allowedColumns = [
            'id','tenant_id','title','slug','excerpt','content',
            'featured_image','featured_image_alt','meta','seo_data',
            'status','visibility','password',
            'published_at','scheduled_at','expires_at',
            'reading_time','word_count','view_count','comment_count',
            'rating_average','rating_count','is_featured','is_sticky',
            'allow_comments','allow_sharing','allow_rating',
            'author_name','author_bio','author_avatar','author_id',
            'created_by','updated_by','reviewed_by','reviewed_at','review_notes',
            'created_at','updated_at','deleted_at',
        ];

        // Sélection
        $select = $allowedColumns;
        if (!empty($validated['fields'])) {
            $requested = $csv($validated['fields'])->all();
            $sel = array_values(array_intersect($allowedColumns, $requested));
            if ($sel) $select = $sel;
        }
        $select = array_values(array_diff($select, ['share_count'])); // Exclure share_count

        // Champs requis pour politiques
        $requiredForPolicies = ['id','status','published_at','expires_at','password','slug','view_count','visibility','author_id'];
        $select = array_values(array_unique(array_merge($select, $requiredForPolicies)));

        // Relations
        $relationsAllowed = [
            'categories','tags','media','comments','approvedComments','ratings','shares','history',
            'author','createdBy','updatedBy','reviewedBy',
        ];
        $includes = $relationsAllowed;
        if (!empty($validated['include'])) {
            $asked = $csv($validated['include'])->all();
            $inc   = array_values(array_intersect($relationsAllowed, $asked));
            if ($inc) $includes = $inc;
        }

        // Requête
        $base = Article::query()
            ->from($articleTable)
            ->select($select)
            ->with($includes)
            ->withCount([
                'shares as share_count' => fn($q) => $q,
                'comments','approvedComments','ratings','history','media','tags','categories'
            ])
            ->withAvg('ratings','rating');

        // Filtre statut (contenu public par défaut)
        if (!empty($validated['status'])) {
            $base->where($articleTable.'.status', $validated['status']);
        } else {
            $base->where($articleTable.'.status', 'published')
                ->whereNotNull($articleTable.'.published_at')
                ->where($articleTable.'.published_at', '<=', now())
                ->where(function ($q) use ($articleTable) {
                    $q->whereNull($articleTable.'.expires_at')
                      ->orWhere($articleTable.'.expires_at', '>', now());
                });
        }

        // id numérique ou slug
        $isNumeric = ctype_digit($idOrSlug);
        if ($isNumeric) {
            $base->where(function ($q) use ($articleTable, $idOrSlug) {
                $q->where($articleTable.'.id', (int) $idOrSlug)
                  ->orWhere($articleTable.'.slug', $idOrSlug);
            });
        } else {
            $base->where($articleTable.'.slug', $idOrSlug);
        }

        $article = $base->firstOrFail();

        // ----- Contrôle d'accès -----
        $providedPassword = $validated['password'] ?? $request->header('X-Article-Password');

        $vis = $article->visibility instanceof \BackedEnum
            ? $article->visibility->value
            : (string) $article->visibility;

        $isPrivate = ($vis === ArticleVisibility::PRIVATE->value);
        $isPwd     = ($vis === ArticleVisibility::PASSWORD_PROTECTED->value);

        // **Privé => permission requise**
        if ($isPrivate && !$this->userHasPermissionToViewPrivateArticle($request, $article)) {
            return response()->json([
                'message'    => 'This article is private.',
                'code'       => 'private',
                'visibility' => ArticleVisibility::PRIVATE->value,
            ], 403);
        }

        // **Protégé par mot de passe**
        if ($isPwd) {
            if ($providedPassword === null || $providedPassword === '') {
                return response()->json([
                    'message'    => 'Password required or incorrect.',
                    'code'       => 'password_required',
                    'visibility' => ArticleVisibility::PASSWORD_PROTECTED->value,
                ], 403);
            }

            $stored = (string) $article->password;
            $looksHashed = str_starts_with($stored, '$2y$')
                        || str_starts_with($stored, '$argon2i$')
                        || str_starts_with($stored, '$argon2id$');

            $ok = $looksHashed
                ? Hash::check($providedPassword, $stored)
                : hash_equals($stored, $providedPassword);

            if (!$ok) {
                return response()->json([
                    'message'    => 'Password required or incorrect.',
                    'code'       => 'password_incorrect',
                    'visibility' => ArticleVisibility::PASSWORD_PROTECTED->value,
                ], 403);
            }
        }

        // Incrément vue
        $viewIncremented = false;
        if ($request->boolean('increment_view')) {
            $dedupeKey = sha1(($request->ip() ?? '0.0.0.0').'|'.($request->userAgent() ?? 'ua'));
            $viewIncremented = app(\App\Services\ArticleCountersService::class)
                ->incrementView($article, 1, $dedupeKey, 300);
            if ($viewIncremented) {
                $article->view_count = (int) ($article->view_count ?? 0) + 1;
            }
        }

        // Normalisation métriques
        if ($article->getAttribute('comment_count') === null) {
            if ($article->getAttribute('approved_comments_count') !== null) {
                $article->setAttribute('comment_count', (int) $article->getAttribute('approved_comments_count'));
            } elseif ($article->getAttribute('comments_count') !== null) {
                $article->setAttribute('comment_count', (int) $article->getAttribute('comments_count'));
            }
        }
        if ($article->getAttribute('rating_average') === null && $article->getAttribute('ratings_avg_rating') !== null) {
            $article->setAttribute('rating_average', (float) $article->getAttribute('ratings_avg_rating'));
        }

        $article->setAttribute('locked', false);
        $article->setAttribute('reason', null);
        $article->makeHidden(['password']);

        return response()->json([
            'data' => $article,
            'meta' => [
                'relations_included' => implode(',', $includes),
                'filters' => [
                    'status' => $validated['status'] ?? 'published',
                ],
                'view_incremented' => $viewIncremented,
            ],
        ]);
    }

    /* =========================================================
     * UNLOCK (POST /articles/{idOrSlug}/unlock)
     * ========================================================= */
    public function unlock(Request $request, string $idOrSlug): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|max:255',
            'include'  => 'nullable|string',
            'fields'   => 'nullable|string',
        ]);

        // On réutilise show() mais en forçant le password depuis le body
        $request->merge([
            'password' => $validated['password'],
            'include'  => $validated['include'] ?? $request->get('include'),
            'fields'   => $validated['fields']  ?? $request->get('fields'),
        ]);

        return $this->show($request, $idOrSlug);
    }

    /* =========================================================
     * RÉSUMÉ NOTES (utilisé par le Visualiseur)
     * ========================================================= */
    public function ratingsSummary(Request $request, string $id): JsonResponse
    {
        $article = Article::query()->findOrFail($id);

        $avg = (float) ($article->ratings()->avg('rating') ?? 0);
        $cnt = (int)   ($article->ratings()->count() ?? 0);

        $myRating = null;
        $myReview = null;

        $user = $this->currentUser($request);
        if ($user) {
            $mine = $article->ratings()->where('user_id', $user->id)->first();
            if ($mine) {
                $myRating = (int) $mine->rating;
                $myReview = (string) ($mine->review ?? '');
            }
        }

        return response()->json([
            'data' => [
                'rating_average' => $avg,
                'rating_count'   => $cnt,
                'my_rating'      => $myRating,
                'my_review'      => $myReview,
            ],
        ]);
    }

    /* =========================================================
     * HELPERS AUTH / PERMISSIONS
     * ========================================================= */

    /**
     * Récupère l'utilisateur courant (Sanctum / Session)
     */
    private function currentUser(Request $request)
    {
        return $request->user()
            ?? Auth::guard('sanctum')->user()
            ?? Auth::user();
    }

    /**
     * Log lisible des rôles & permissions de l'utilisateur courant
     */
    private function logCurrentUser(Request $request): void
    {
        $u = $this->currentUser($request);

        if (!$u) {
            Log::warning('No authenticated user on this request', [
                'ip'         => $request->ip(),
                'route'      => optional($request->route())->uri(),
                'user_agent' => $request->userAgent(),
            ]);
            return;
        }

        // Rôles
        $roles = method_exists($u, 'roles')
            ? $u->roles()->pluck('name')->values()->all()
            : (property_exists($u, 'roles') ? collect($u->roles)->pluck('name')->values()->all() : []);

        // Permissions : on collecte name/action/resource si dispo
        $permTokens = $this->permissionTokensFromUser($u)->values()->all();

        // Abilities du token (Sanctum)
        $abilities = method_exists($u, 'currentAccessToken') && $u->currentAccessToken()
            ? $u->currentAccessToken()->abilities
            : [];

        Log::info('Authenticated user roles/permissions', [
            'user_id'    => $u->id ?? null,
            'roles'      => $roles,
            'perms'      => $permTokens,
            'abilities'  => $abilities,
            'ip'         => $request->ip(),
            'route'      => optional($request->route())->uri(),
            'method'     => $request->method(),
        ]);
    }

    /**
     * Extrait une collection de "tokens" de permission (lowercase)
     * depuis name/action/resource (relations custom) et Spatie si présent.
     */
    private function permissionTokensFromUser($user): \Illuminate\Support\Collection
    {
        $tokens = collect();

        // Custom relation permissions(): name/action/resource
        try {
            if (method_exists($user, 'permissions')) {
                $perms = $user->permissions()->get(['name','action','resource']);
                $tokens = $tokens->merge(
                    collect($perms)->flatMap(function ($p) {
                        return [
                            mb_strtolower((string)($p->name ?? '')),
                            mb_strtolower((string)($p->action ?? '')),
                            mb_strtolower((string)($p->resource ?? '')),
                        ];
                    })
                );
            } elseif (property_exists($user, 'permissions')) {
                $perms = collect($user->permissions);
                $tokens = $tokens->merge(
                    $perms->flatMap(function ($p) {
                        return [
                            mb_strtolower((string)($p->name ?? '')),
                            mb_strtolower((string)($p->action ?? '')),
                            mb_strtolower((string)($p->resource ?? '')),
                        ];
                    })
                );
            }
        } catch (\Throwable $e) {
            // no-op
        }

        // Spatie (facultatif) : on ajoute le nom brut si dispo
        try {
            if (method_exists($user, 'getAllPermissions')) {
                $tokens = $tokens->merge(
                    $user->getAllPermissions()->pluck('name')->map(fn($n) => mb_strtolower((string)$n))
                );
            }
        } catch (\Throwable $e) {
            // no-op
        }

        // Nettoyage
        return $tokens->filter()->unique()->values();
    }

    /**
     * Teste si l'utilisateur courant peut voir un article privé.
     * Règle principale: possession d'une permission
     *   - 'articles.read_private' (recommandée)
     *   - 'articles.view_private'
     *   - tolérance FR: "Lire articles privés"
     * Fallbacks:
     *   - auteur de l'article
     *   - rôles forts: Admin/Owner/Super/Manager
     *   - ability Sanctum 'articles.read_private'
     */
    private function userHasPermissionToViewPrivateArticle(Request $request, Article $article): bool
    {
        $user = $this->currentUser($request);
        if (!$user) {
            return false;
        }

        // 1) L'auteur voit toujours
        if (!empty($article->author_id) && (int)$article->author_id === (int)$user->id) {
            return true;
        }

        // 2) Rôles forts (si tu veux autoriser par rôle)
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin','Owner','Super','Manager'])) {
                return true;
            }
            if (method_exists($user, 'roles')) {
                $roles = $user->roles()->pluck('name')->map(fn($r)=>mb_strtolower((string)$r));
                if ($roles->contains(fn($r)=>preg_match('/\b(admin|owner|super|manager)\b/', $r))) {
                    return true;
                }
            } elseif (property_exists($user, 'roles')) {
                $roles = collect($user->roles)->pluck('name')->map(fn($r)=>mb_strtolower((string)$r));
                if ($roles->contains(fn($r)=>preg_match('/\b(admin|owner|super|manager)\b/', $r))) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // no-op
        }

        // 3) Permission explicite (prioritaire)
        //    Spatie: $user->hasPermissionTo('articles.read_private') ou $user->can('articles.read_private')
        try {
            if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('articles.read_private')) {
                return true;
            }
            if (method_exists($user, 'can') && $user->can('articles.read_private')) {
                return true;
            }
        } catch (\Throwable $e) {
            // no-op
        }

        // 4) Permissions "custom" (name/action/resource) => on compare par tokens
        $permTokens = $this->permissionTokensFromUser($user);

        $allows = $permTokens->contains(function ($v) {
            return
                str_contains($v, 'articles.read_private') ||
                str_contains($v, 'articles.view_private') ||
                $v === 'lire articles privés' ||
                preg_match('/(article|articles).*priv(e|é)/', $v);
        });

        if ($allows) {
            return true;
        }

        // 5) Ability Sanctum
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $token = $user->currentAccessToken();
            if (is_array($token->abilities) && (in_array('*', $token->abilities, true) || in_array('articles.read_private', $token->abilities, true))) {
                return true;
            }
        }

        return false;
    }
}
