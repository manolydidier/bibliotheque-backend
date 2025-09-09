<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Models\Article;
use Illuminate\Support\Facades\DB;          // ✅ CORRECT
use Illuminate\Support\Facades\Schema;     // (pour sécuriser les facettes)

class ArticleController extends Controller
{
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
            'sort'           => 'nullable|string', // ex: "published_at,desc;view_count,desc"
            'sort_by'        => 'nullable|string|in:created_at,updated_at,published_at,title,view_count,rating_average',
            'sort_direction' => 'nullable|string|in:asc,desc',

            // Sélection & relations
            'include'        => 'nullable|string',
            'fields'         => 'nullable|string',

            // Facettes
            'include_facets' => 'nullable|boolean',
            'facet_fields'   => 'nullable|string',
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
            'reading_time','word_count','view_count','share_count','comment_count',
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
            ->withCount(['comments','approvedComments','ratings','shares','history','media','tags','categories'])
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

        // Catégories (ids / names / slugs)
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

        // Tags (ids / names / slugs)
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
        $sortable = ['created_at','updated_at','published_at','title','view_count','rating_average'];
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
                $base->orderBy($articleTable.'.'.$k, $d);
            }
        } else {
            $sortBy        = $validated['sort_by']        ?? 'published_at';
            $sortDirection = $validated['sort_direction'] ?? 'desc';
            if ($sortBy === 'published_at') {
                $base->orderBy($articleTable.'.is_sticky','desc')
                     ->orderBy($articleTable.'.is_featured','desc');
            }
            $base->orderBy($articleTable.'.'.$sortBy, $sortDirection);
        }

        // Clone pour facettes (avant pagination)
        $forFacets = clone $base;

        // Pagination
        $perPage   = (int) ($validated['per_page'] ?? 15);
        $paginator = $base->paginate($perPage)->appends($request->query());

        // Facettes (robuste, ne doit jamais planter l’endpoint)
        $facets = null;
        if ($request->boolean('include_facets')) {
            try {
                $fields = $csv($validated['facet_fields'] ?? 'categories,tags,authors')->all();

                // ids de TOUT l'ensemble filtré (pas seulement la page)
                $filteredIds = (clone $forFacets)->select($articleTable.'.id')->pluck('id');

                $facets = [];

                if ($filteredIds->isNotEmpty()) {
                    if (in_array('categories', $fields, true) && Schema::hasTable('categories') && Schema::hasTable('article_category')) {
                        $facets['categories'] = DB::table('article_category')
                            ->whereIn('article_id', $filteredIds)
                            ->join('categories','categories.id','=','article_category.category_id')
                            ->select('categories.id','categories.name', DB::raw('COUNT(*) as count'))
                            ->groupBy('categories.id','categories.name')
                            ->orderByDesc('count')
                            ->get();
                    }

                    if (in_array('tags', $fields, true) && Schema::hasTable('tags') && Schema::hasTable('article_tag')) {
                        $facets['tags'] = DB::table('article_tag')
                            ->whereIn('article_id', $filteredIds)
                            ->join('tags','tags.id','=','article_tag.tag_id')
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
                    'date_from'   => $validated['date_from']   ?? null,
                    'date_to'     => $validated['date_to']     ?? null,
                    'rating_min'  => $validated['rating_min']  ?? null,
                    'rating_max'  => $validated['rating_max']  ?? null,
                    'featured'    => array_key_exists('featured', $validated) ? (bool)$validated['featured'] : null,
                    'sticky'      => array_key_exists('sticky', $validated)   ? (bool)$validated['sticky']   : null,
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

    public function show(Request $request, string $idOrSlug): JsonResponse
{
    // --- Validation des query params ---
    $validated = $request->validate([
        'include'        => 'nullable|string',
        'fields'         => 'nullable|string',
        'status'         => 'nullable|string|in:draft,pending,published,archived',
        'password'       => 'nullable|string|max:255',
        'increment_view' => 'nullable|boolean',
    ]);

    // --- Helpers ---
    $csv = fn($v) => collect(is_array($v) ? $v : explode(',', (string)$v))
        ->map(fn($x) => trim((string)$x))
        ->filter()
        ->values();

    $articleTable = (new Article())->getTable();

    $allowedColumns = [
        'id','tenant_id','title','slug','excerpt','content',
        'featured_image','featured_image_alt','meta','seo_data',
        'status','visibility','password',
        'published_at','scheduled_at','expires_at',
        'reading_time','word_count','view_count','share_count','comment_count',
        'rating_average','rating_count',
        'is_featured','is_sticky','allow_comments','allow_sharing','allow_rating',
        'author_name','author_bio','author_avatar','author_id',
        'created_by','updated_by','reviewed_by','reviewed_at','review_notes',
        'created_at','updated_at','deleted_at',
    ];

    // Colonnes sélectionnées (on force celles nécessaires aux vérifs métier)
    $select = $allowedColumns;
    if (!empty($validated['fields'])) {
        $requested = $csv($validated['fields'])->all();
        $sel = array_values(array_intersect($allowedColumns, $requested));
        if ($sel) $select = $sel;
    }
    // Toujours inclure ces colonnes pour la logique interne (sans les exposer si besoin)
    $requiredForPolicies = ['id','status','published_at','expires_at','password'];
    $select = array_values(array_unique(array_merge($select, $requiredForPolicies)));

    // Relations autorisées
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

    // --- Base query ---
    $base = Article::query()
        ->from($articleTable)
        ->select($select)
        ->with($includes)
        ->withCount(['comments','approvedComments','ratings','shares','history','media','tags','categories'])
        ->withAvg('ratings','rating');

    // Statut (par défaut : publié et non expiré)
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

    // Ciblage par id ou slug
    $isId = ctype_digit($idOrSlug);
    $base->where($articleTable.'.'.($isId ? 'id' : 'slug'), $idOrSlug);

    // Récupération
    $article = $base->firstOrFail();

    // Protection par mot de passe (si défini sur l'article)
    $providedPassword = $validated['password'] ?? null;
    if (!empty($article->password) && $article->password !== $providedPassword) {
        return response()->json(['message' => 'Password required or incorrect.'], 403);
    }

    // Incrémenter le compteur de vues si demandé
    if ($request->boolean('increment_view')) {
        Article::where('id', $article->id)->increment('view_count');
        // refléter la nouvelle valeur dans la réponse
        $article->view_count = (int) ($article->view_count ?? 0) + 1;
    }

    // Ne jamais exposer le champ password dans la réponse
    $article->makeHidden(['password']);

    return response()->json([
        'data' => $article,
        'meta' => [
            'relations_included' => implode(',', $includes),
            'filters' => [
                'status' => $validated['status'] ?? 'published',
            ],
        ],
    ]);
}

}
