<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ArticleCollection extends ResourceCollection
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
        $canCreate = $user && ($user->hasPermissionTo('articles.create') || $isAdmin);

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
                'total_articles' => $this->total(),
                'total_published' => $this->collection->where('status', 'published')->count(),
                'total_draft' => $this->collection->where('status', 'draft')->count(),
                'total_pending' => $this->collection->where('status', 'pending')->count(),
                'total_featured' => $this->collection->where('is_featured', true)->count(),
                'total_sticky' => $this->collection->where('is_sticky', true)->count(),
                'average_reading_time' => round($this->collection->avg('reading_time'), 1),
                'average_rating' => round($this->collection->avg('average_rating'), 1),
            ],

            // Liens HATEOAS
            '_links' => [
                'self' => [
                    'href' => $request->url(),
                    'method' => 'GET',
                ],
                'create' => $this->when($canCreate, [
                    'href' => route('api.articles.store'),
                    'method' => 'POST',
                ]),
                'search' => [
                    'href' => route('api.articles.search'),
                    'method' => 'GET',
                ],
                'categories' => [
                    'href' => route('api.categories.index'),
                    'method' => 'GET',
                ],
                'tags' => [
                    'href' => route('api.tags.index'),
                    'method' => 'GET',
                ],
                'analytics' => [
                    'href' => route('api.analytics.overview'),
                    'method' => 'GET',
                ],
            ],

            // Actions disponibles
            '_actions' => [
                'can_create' => $canCreate,
                'can_bulk_import' => $canCreate,
                'can_bulk_export' => $user && ($user->hasPermissionTo('articles.export') || $isAdmin),
                'can_bulk_delete' => $user && ($user->hasPermissionTo('articles.delete') || $isAdmin),
                'can_bulk_publish' => $user && ($user->hasPermissionTo('articles.publish') || $isAdmin),
            ],

            // Filtres appliqués
            '_filters' => [
                'status' => $request->get('status'),
                'visibility' => $request->get('visibility'),
                'category' => $request->get('category'),
                'tag' => $request->get('tag'),
                'author' => $request->get('author'),
                'featured' => $request->get('featured'),
                'sticky' => $request->get('sticky'),
                'search' => $request->get('search'),
                'sort' => $request->get('sort', 'created_at'),
                'order' => $request->get('order', 'desc'),
            ],

            // Informations de performance
            '_performance' => [
                'query_time' => $this->collection->first()?->getQueryTime() ?? null,
                'cache_hit' => $this->collection->first()?->wasRecentlyCreated ?? false,
                'eager_loaded' => $this->collection->first()?->getRelations() ?? [],
            ],
        ];
    }
}
