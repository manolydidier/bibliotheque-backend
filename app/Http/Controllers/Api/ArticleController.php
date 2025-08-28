<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\ArticleCollection;
use App\Models\Article;
use App\Services\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleService $articleService
    ) {
        $this->middleware('auth:sanctum')->except(['index', 'show', 'search']);
        $this->middleware('throttle:60,1')->only(['store', 'update', 'destroy']);
    }

    /**
     * Display a listing of articles with advanced filtering and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'tag_id' => 'nullable|integer|exists:tags,id',
            'author_id' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|string|in:draft,pending,published,archived',
            'featured' => 'nullable|boolean',
            'sticky' => 'nullable|boolean',
            'sort_by' => 'nullable|string|in:created_at,updated_at,published_at,title,view_count,rating_average',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'include' => 'nullable|string',
            'fields' => 'nullable|string',
        ]);

        $cacheKey = 'articles_' . md5(serialize($request->all()));
        
        return Cache::remember($cacheKey, 300, function () use ($request) {
            $query = Article::query()
                ->with(['categories', 'tags', 'author', 'media'])
                ->withCount(['comments', 'ratings', 'shares'])
                ->withAvg('ratings', 'rating');

            // Apply filters
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            if ($request->filled('category_id')) {
                $query->byCategory($request->category_id);
            }

            if ($request->filled('tag_id')) {
                $query->byTag($request->tag_id);
            }

            if ($request->filled('author_id')) {
                $query->byAuthor($request->author_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            } else {
                // Default to published articles for public access
                $query->published()->public();
            }

            if ($request->boolean('featured')) {
                $query->featured();
            }

            if ($request->boolean('sticky')) {
                $query->sticky();
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'published_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            if ($sortBy === 'published_at') {
                $query->orderBy('is_sticky', 'desc')
                      ->orderBy('is_featured', 'desc')
                      ->orderBy('published_at', $sortDirection);
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Apply tenant filtering if needed
            if (Auth::check() && Auth::user()->tenant_id) {
                $query->byTenant(Auth::user()->tenant_id);
            }

            $perPage = $request->get('per_page', 15);
            $articles = $query->paginate($perPage);

            return ArticleCollection::make($articles);
        });
    }

    /**
     * Store a newly created article.
     */
    public function store(StoreArticleRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $article = $this->articleService->createArticle($request->validated(), Auth::user());

            DB::commit();

            return response()->json([
                'message' => 'Article créé avec succès',
                'data' => new ArticleResource($article->load(['categories', 'tags', 'author'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erreur lors de la création de l\'article',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Display the specified article.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $article = Article::where('slug', $slug)
            ->with(['categories', 'tags', 'author', 'media', 'approvedComments.user'])
            ->withCount(['comments', 'ratings', 'shares'])
            ->withAvg('ratings', 'rating')
            ->firstOrFail();

        // Check if user can view this article
        if (!$article->canBeViewedBy(Auth::user())) {
            return response()->json([
                'message' => 'Accès non autorisé à cet article',
            ], 403);
        }

        // Increment view count (with rate limiting)
        $cacheKey = "article_view_{$article->id}_" . ($request->ip() ?? 'unknown');
        if (!Cache::has($cacheKey)) {
            $article->incrementViewCount();
            Cache::put($cacheKey, true, 3600); // 1 hour
        }

        return response()->json([
            'data' => new ArticleResource($article),
        ]);
    }

    /**
     * Update the specified article.
     */
    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        // Check permissions
        if (!Auth::user()->can('update', $article)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à modifier cet article',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $updatedArticle = $this->articleService->updateArticle($article, $request->validated(), Auth::user());

            DB::commit();

            return response()->json([
                'message' => 'Article mis à jour avec succès',
                'data' => new ArticleResource($updatedArticle->load(['categories', 'tags', 'author'])),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'article',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Remove the specified article.
     */
    public function destroy(Article $article): JsonResponse
    {
        // Check permissions
        if (!Auth::user()->can('delete', $article)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à supprimer cet article',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $this->articleService->deleteArticle($article);

            DB::commit();

            return response()->json([
                'message' => 'Article supprimé avec succès',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'article',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Search articles with advanced filters.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
            'filters' => 'nullable|array',
        ]);

        $query = Article::query()
            ->published()
            ->public()
            ->with(['categories', 'tags', 'author'])
            ->withCount(['comments', 'ratings', 'shares'])
            ->withAvg('ratings', 'rating');

        // Apply search
        $query->search($request->q);

        // Apply additional filters
        if ($request->filled('filters')) {
            $this->applySearchFilters($query, $request->filters);
        }

        $perPage = $request->get('per_page', 20);
        $articles = $query->ordered()->paginate($perPage);

        return ArticleCollection::make($articles);
    }

    /**
     * Publish an article.
     */
    public function publish(Article $article): JsonResponse
    {
        if (!Auth::user()->can('publish', $article)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à publier cet article',
            ], 403);
        }

        try {
            $this->articleService->publishArticle($article, Auth::user());

            return response()->json([
                'message' => 'Article publié avec succès',
                'data' => new ArticleResource($article->load(['categories', 'tags', 'author'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la publication de l\'article',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Unpublish an article.
     */
    public function unpublish(Article $article): JsonResponse
    {
        if (!Auth::user()->can('publish', $article)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à dépublier cet article',
            ], 403);
        }

        try {
            $this->articleService->unpublishArticle($article, Auth::user());

            return response()->json([
                'message' => 'Article dépublié avec succès',
                'data' => new ArticleResource($article->load(['categories', 'tags', 'author'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la dépublication de l\'article',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Duplicate an article.
     */
    public function duplicate(Article $article): JsonResponse
    {
        if (!Auth::user()->can('create', Article::class)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à créer des articles',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $duplicatedArticle = $this->articleService->duplicateArticle($article, Auth::user());

            DB::commit();

            return response()->json([
                'message' => 'Article dupliqué avec succès',
                'data' => new ArticleResource($duplicatedArticle->load(['categories', 'tags', 'author'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erreur lors de la duplication de l\'article',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Toggle featured status of an article.
     */
    public function toggleFeatured(Article $article): JsonResponse
    {
        if (!Auth::user()->can('update', $article)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à modifier cet article',
            ], 403);
        }

        try {
            $article->update(['is_featured' => !$article->is_featured]);

            return response()->json([
                'message' => $article->is_featured ? 'Article mis en avant' : 'Article retiré des mises en avant',
                'data' => new ArticleResource($article->load(['categories', 'tags', 'author'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la modification du statut',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Get article statistics.
     */
    public function stats(Article $article): JsonResponse
    {
        if (!Auth::user()->can('viewStats', $article)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à voir les statistiques de cet article',
            ], 403);
        }

        $stats = $this->articleService->getArticleStats($article);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Apply search filters to the query.
     */
    private function applySearchFilters($query, array $filters): void
    {
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'category_id':
                    $query->byCategory($value);
                    break;
                case 'tag_id':
                    $query->byTag($value);
                    break;
                case 'author_id':
                    $query->byAuthor($value);
                    break;
                case 'date_from':
                    $query->where('published_at', '>=', $value);
                    break;
                case 'date_to':
                    $query->where('published_at', '<=', $value);
                    break;
                case 'min_rating':
                    $query->where('rating_average', '>=', $value);
                    break;
                case 'max_reading_time':
                    $query->where('reading_time', '<=', $value);
                    break;
            }
        }
    }
}
