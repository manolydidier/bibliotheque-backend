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
use Illuminate\Support\Facades\Cache as CacheFacade;

class ArticleController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
   // (tu peux enlever le "use AuthorizesRequests" ici, il est déjà dans le contrôleur de base)
    public function __construct(private readonly ArticleService $articleService)
    {
        // Auth publique limitée
        $this->middleware('auth:sanctum')->except(['index', 'show', 'search']);

        // Throttle propre (RateLimiter 'article-actions' défini dans AppServiceProvider)
        $this->middleware('throttle:article-actions')->only(['store', 'update', 'destroy']);

        // Autorisations via Policy (index/show/store/update/destroy)
        $this->authorizeResource(\App\Models\Article::class, 'article');

        // Actions custom protégées par la policy (si tu les utilises)
        $this->middleware('can:publish,article')->only(['publish', 'unpublish', 'bulkPublish', 'bulkUnpublish']);
        $this->middleware('can:viewStats,article')->only(['stats']);
        $this->middleware('can:update,article')->only(['toggleFeatured', 'generatePreviewToken', 'bulkMoveCategory', 'bulkAddTags', 'bulkRemoveTags']);
        $this->middleware('can:create,App\Models\Article')->only(['duplicate', 'import']);
        $this->middleware('can:delete,article')->only(['destroy', 'bulkArchive', 'bulkDelete']);
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

            return ArticleResource::collection($articles);
        });
    }

    /**
     * Store a newly created article.
     */
    public function store(StoreArticleRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $article = $this->articleService->createArticle($request->validated(), $request->user());
            DB::commit();

            return response()->json([
                'message' => 'Article créé avec succès',
                'data' => new ArticleResource($article->load(['categories', 'tags', 'author'])),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création de l\'article',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * Display the specified article.
     * Binding {article:slug} requis côté routes.
     */
    public function show(Request $request, Article $article): JsonResponse
    {
        // Policy: ArticlePolicy@view gère public/private
        $this->authorize('view', $article);

        // Chargements
        $article->load(['categories', 'tags', 'author', 'media', 'approvedComments.user'])
                ->loadCount(['comments', 'ratings', 'shares'])
                ->loadAvg('ratings', 'rating');

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
        $this->authorize('update', $article);

        DB::beginTransaction();
        try {
            $updatedArticle = $this->articleService->updateArticle($article, $request->validated(), $request->user());
            DB::commit();

            return response()->json([
                'message' => 'Article mis à jour avec succès',
                'data' => new ArticleResource($updatedArticle->load(['categories', 'tags', 'author'])),
            ]);

        } catch (\Throwable $e) {
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
        $this->authorize('delete', $article);

        DB::beginTransaction();
        try {
            $this->articleService->deleteArticle($article);
            DB::commit();

            return response()->json([
                'message' => 'Article supprimé avec succès',
            ]);

        } catch (\Throwable $e) {
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

        return ArticleResource::collection($articles);
    }

    /**
     * Publish an article.
     */
    public function publish(Article $article): JsonResponse
    {
        $this->authorize('publish', $article);

        try {
            $this->articleService->publishArticle($article, request()->user());

            return response()->json([
                'message' => 'Article publié avec succès',
                'data' => new ArticleResource($article->load(['categories', 'tags', 'author'])),
            ]);

        } catch (\Throwable $e) {
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
        $this->authorize('publish', $article);

        try {
            $this->articleService->unpublishArticle($article, request()->user());

            return response()->json([
                'message' => 'Article dépublié avec succès',
                'data' => new ArticleResource($article->load(['categories', 'tags', 'author'])),
            ]);

        } catch (\Throwable $e) {
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
        $this->authorize('create', Article::class);

        DB::beginTransaction();
        try {
            $duplicatedArticle = $this->articleService->duplicateArticle($article, request()->user());
            DB::commit();

            return response()->json([
                'message' => 'Article dupliqué avec succès',
                'data' => new ArticleResource($duplicatedArticle->load(['categories', 'tags', 'author'])),
            ], 201);

        } catch (\Throwable $e) {
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
        $this->authorize('update', $article);

        try {
            $article->update(['is_featured' => !$article->is_featured]);

            return response()->json([
                'message' => $article->is_featured ? 'Article mis en avant' : 'Article retiré des mises en avant',
                'data' => new ArticleResource($article->load(['categories', 'tags', 'author'])),
            ]);

        } catch (\Throwable $e) {
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
        $this->authorize('viewStats', $article);

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

    /**
     * Import/export & helpers
     */
    public function bulkPublish(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:articles,id'],
        ]);

        $articles = Article::whereIn('id', $data['ids'])->get();
        foreach ($articles as $article) {
            if ($request->user()->can('publish', $article)) {
                $this->articleService->publishArticle($article, $request->user());
            }
        }

        return response()->json(['message' => 'Articles publiés']);
    }

    public function bulkUnpublish(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:articles,id'],
        ]);

        $articles = Article::whereIn('id', $data['ids'])->get();
        foreach ($articles as $article) {
            if ($request->user()->can('publish', $article)) {
                $this->articleService->unpublishArticle($article, $request->user());
            }
        }

        return response()->json(['message' => 'Articles dépubliés']);
    }

    public function bulkArchive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:articles,id'],
        ]);

        $articles = Article::whereIn('id', $data['ids'])->get();
        foreach ($articles as $article) {
            if ($request->user()->can('delete', $article)) {
                $article->delete();
            }
        }

        return response()->json(['message' => 'Articles archivés']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:articles,id'],
            'force' => ['nullable', 'boolean'],
        ]);

        $force = (bool) ($data['force'] ?? false);
        $articles = Article::withTrashed()->whereIn('id', $data['ids'])->get();
        foreach ($articles as $article) {
            if ($request->user()->can('delete', $article)) {
                $force ? $article->forceDelete() : $article->delete();
            }
        }

        return response()->json(['message' => $force ? 'Articles supprimés définitivement' : 'Articles supprimés']);
    }

    public function bulkMoveCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:articles,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $articles = Article::whereIn('id', $data['ids'])->get();
        foreach ($articles as $article) {
            if (!$request->user()->can('update', $article)) {
                continue;
            }
            $existing = $article->categories()->pluck('categories.id')->toArray();
            $sync = array_fill_keys($existing, ['is_primary' => false, 'sort_order' => 0]);
            $sync[(int) $data['category_id']] = ['is_primary' => true, 'sort_order' => 0];
            $article->categories()->sync($sync);
        }

        return response()->json(['message' => 'Articles déplacés de catégorie']);
    }

    public function bulkAddTags(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:articles,id'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]);

        $articles = Article::whereIn('id', $data['ids'])->get();
        foreach ($articles as $article) {
            if (!$request->user()->can('update', $article)) {
                continue;
            }
            $article->tags()->syncWithoutDetaching(collect($data['tags'])->mapWithKeys(
                fn ($id) => [(int) $id => ['sort_order' => 0]]
            )->toArray());
        }

        return response()->json(['message' => 'Tags ajoutés']);
    }

    public function bulkRemoveTags(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:articles,id'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]);

        $articles = Article::whereIn('id', $data['ids'])->get();
        foreach ($articles as $article) {
            if (!$request->user()->can('update', $article)) {
                continue;
            }
            $article->tags()->detach($data['tags']);
        }

        return response()->json(['message' => 'Tags retirés']);
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string'],
            'items.*.content' => ['required', 'string'],
        ]);

        $created = [];
        foreach ($data['items'] as $item) {
            if ($request->user()->can('create', Article::class)) {
                $created[] = $this->articleService->createArticle($item, $request->user());
            }
        }

        return response()->json(['message' => 'Import terminé', 'count' => count($created)]);
    }

    public function export(Request $request): JsonResponse
    {
        $articles = Article::with(['categories', 'tags', 'author'])->get();
        return response()->json(['data' => ArticleResource::collection($articles)]);
    }

    public function downloadTemplate(): JsonResponse
    {
        return response()->json([
            'template' => [
                'items' => [
                    ['title' => 'Titre', 'content' => 'Contenu ...', 'status' => 'draft'],
                ],
            ],
        ]);
    }

    public function searchSuggestions(Request $request): JsonResponse
    {
        $q = (string) $request->validate(['q' => ['nullable', 'string']])['q'] ?? '';
        $titles = Article::query()->where('title', 'like', "%{$q}%")->limit(10)->pluck('title');
        return response()->json(['data' => $titles]);
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $q = (string) $request->validate(['q' => ['nullable', 'string']])['q'] ?? '';
        $articles = Article::query()->where('title', 'like', "%{$q}%")->limit(10)->get(['id', 'title', 'slug']);
        return response()->json(['data' => $articles]);
    }

    public function robots(): \Illuminate\Http\Response
    {
        $content = "User-agent: *\nAllow: /\n";
        return response($content, 200, ['Content-Type' => 'text/plain']);
    }

    public function metaTags(Article $article): JsonResponse
    {
        return response()->json([
            'title' => $article->title,
            'description' => $article->meta['description'] ?? null,
            'keywords' => $article->meta['keywords'] ?? null,
        ]);
    }

    public function preview(Article $article): JsonResponse
    {
        // Autorisation standard (évite le rouge sur Auth::user()->can)
        $this->authorize('view', $article);

        return response()->json([
            'data' => new ArticleResource($article->load(['categories', 'tags', 'author'])),
        ]);
    }

    public function generatePreviewToken(Article $article): JsonResponse
    {
        $this->authorize('update', $article);

        $token = bin2hex(random_bytes(16));
        CacheFacade::put('preview_'.$article->id.'_'.$token, true, 900);
        return response()->json(['token' => $token]);
    }

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function databaseHealth(): JsonResponse
    {
        try {
            Article::query()->select('id')->limit(1)->get();
            return response()->json(['database' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json(['database' => 'error'], 500);
        }
    }

    public function cacheHealth(): JsonResponse
    {
        try {
            CacheFacade::put('health_check', '1', 5);
            $ok = CacheFacade::get('health_check') === '1';
            return response()->json(['cache' => $ok ? 'ok' : 'error'], $ok ? 200 : 500);
        } catch (\Throwable $e) {
            return response()->json(['cache' => 'error'], 500);
        }
    }
}
