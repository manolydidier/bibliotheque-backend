<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TagCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user && $user->hasRole(['admin', 'super_admin']);
        $canCreate = $user && ($user->hasPermissionTo('tags.create') || $isAdmin);

        return [
            'data' => $this->collection,
            
            // Métadonnées de pagination
            'pagination' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
                'has_more_pages' => $this->hasMorePages(),
            ],

            // Liens de pagination
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],

            // Métadonnées de la collection
            'meta' => [
                'total_tags' => $this->total(),
                'total_active' => $this->collection->where('is_active', true)->count(),
                'total_popular' => $this->collection->where('usage_count', '>', 10)->count(),
                'total_unused' => $this->collection->where('usage_count', 0)->count(),
                'total_articles' => $this->collection->sum('articles_count'),
                'average_usage' => round($this->collection->avg('usage_count'), 1),
                'most_used_tag' => $this->collection->sortByDesc('usage_count')->first()?->name,
                'least_used_tag' => $this->collection->sortBy('usage_count')->first()?->name,
            ],

            // Statistiques d'utilisation
            'usage_stats' => [
                'high_usage' => $this->collection->where('usage_count', '>', 50)->count(),
                'medium_usage' => $this->collection->whereBetween('usage_count', [11, 50])->count(),
                'low_usage' => $this->collection->whereBetween('usage_count', [1, 10])->count(),
                'unused' => $this->collection->where('usage_count', 0)->count(),
            ],

            // Liens HATEOAS
            '_links' => [
                'self' => [
                    'href' => $request->url(),
                    'method' => 'GET',
                ],
                'create' => $this->when($canCreate, [
                    'href' => route('api.tags.store'),
                    'method' => 'POST',
                ]),
                'popular' => [
                    'href' => route('api.tags.popular'),
                    'method' => 'GET',
                ],
                'unused' => [
                    'href' => route('api.tags.unused'),
                    'method' => 'GET',
                ],
                'articles' => [
                    'href' => route('api.articles.index'),
                    'method' => 'GET',
                ],
                'categories' => [
                    'href' => route('api.categories.index'),
                    'method' => 'GET',
                ],
            ],

            // Actions disponibles
            '_actions' => [
                'can_create' => $canCreate,
                'can_bulk_import' => $canCreate,
                'can_bulk_export' => $user && ($user->hasPermissionTo('tags.export') || $isAdmin),
                'can_bulk_delete' => $user && ($user->hasPermissionTo('tags.delete') || $isAdmin),
                'can_bulk_toggle_active' => $user && ($user->hasPermissionTo('tags.manage') || $isAdmin),
                'can_merge' => $user && ($user->hasPermissionTo('tags.manage') || $isAdmin),
                'can_cleanup' => $user && ($user->hasPermissionTo('tags.manage') || $isAdmin),
            ],

            // Filtres appliqués
            '_filters' => [
                'active' => $request->get('active'),
                'popular' => $request->get('popular'),
                'usage_min' => $request->get('usage_min'),
                'usage_max' => $request->get('usage_max'),
                'search' => $request->get('search'),
                'sort' => $request->get('sort', 'usage_count'),
                'order' => $request->get('order', 'desc'),
            ],

            // Statistiques de performance
            '_performance' => [
                'query_time' => $this->collection->first()?->getQueryTime() ?? null,
                'cache_hit' => $this->collection->first()?->wasRecentlyCreated ?? false,
                'eager_loaded' => $this->collection->first()?->getRelations() ?? [],
            ],

            // Suggestions d'optimisation
            '_optimization' => [
                'unused_tags_count' => $this->collection->where('usage_count', 0)->count(),
                'low_usage_tags_count' => $this->collection->whereBetween('usage_count', [1, 5])->count(),
                'suggested_cleanup' => $this->collection->where('usage_count', 0)->count() > 10,
                'suggested_merge' => $this->collection->where('usage_count', '>', 0)->count() > 100,
            ],
        ];
    }
}
