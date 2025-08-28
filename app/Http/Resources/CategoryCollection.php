<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CategoryCollection extends ResourceCollection
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
        $canCreate = $user && ($user->hasPermissionTo('categories.create') || $isAdmin);

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
                'total_categories' => $this->total(),
                'total_active' => $this->collection->where('is_active', true)->count(),
                'total_featured' => $this->collection->where('is_featured', true)->count(),
                'total_root' => $this->collection->whereNull('parent_id')->count(),
                'total_with_children' => $this->collection->where('children_count', '>', 0)->count(),
                'total_articles' => $this->collection->sum('articles_count'),
                'average_articles_per_category' => round($this->collection->avg('articles_count'), 1),
            ],

            // Hiérarchie des catégories
            'hierarchy' => [
                'root_categories' => $this->collection->whereNull('parent_id')->count(),
                'max_depth' => $this->getMaxDepth(),
                'total_levels' => $this->getTotalLevels(),
            ],

            // Liens HATEOAS
            '_links' => [
                'self' => [
                    'href' => $request->url(),
                    'method' => 'GET',
                ],
                'create' => $this->when($canCreate, [
                    'href' => route('api.categories.store'),
                    'method' => 'POST',
                ]),
                'tree' => [
                    'href' => route('api.categories.tree'),
                    'method' => 'GET',
                ],
                'articles' => [
                    'href' => route('api.articles.index'),
                    'method' => 'GET',
                ],
                'tags' => [
                    'href' => route('api.tags.index'),
                    'method' => 'GET',
                ],
            ],

            // Actions disponibles
            '_actions' => [
                'can_create' => $canCreate,
                'can_bulk_import' => $canCreate,
                'can_bulk_export' => $user && ($user->hasPermissionTo('categories.export') || $isAdmin),
                'can_bulk_delete' => $user && ($user->hasPermissionTo('categories.delete') || $isAdmin),
                'can_bulk_toggle_active' => $user && ($user->hasPermissionTo('categories.manage') || $isAdmin),
                'can_reorder' => $user && ($user->hasPermissionTo('categories.manage') || $isAdmin),
            ],

            // Filtres appliqués
            '_filters' => [
                'active' => $request->get('active'),
                'featured' => $request->get('featured'),
                'parent' => $request->get('parent'),
                'search' => $request->get('search'),
                'sort' => $request->get('sort', 'sort_order'),
                'order' => $request->get('order', 'asc'),
            ],

            // Statistiques de performance
            '_performance' => [
                'query_time' => $this->collection->first()?->getQueryTime() ?? null,
                'cache_hit' => $this->collection->first()?->wasRecentlyCreated ?? false,
                'eager_loaded' => $this->collection->first()?->getRelations() ?? [],
            ],
        ];
    }

    /**
     * Obtenir la profondeur maximale des catégories
     */
    private function getMaxDepth(): int
    {
        $maxDepth = 0;
        foreach ($this->collection as $category) {
            $depth = $this->calculateDepth($category);
            $maxDepth = max($maxDepth, $depth);
        }
        return $maxDepth;
    }

    /**
     * Obtenir le nombre total de niveaux
     */
    private function getTotalLevels(): int
    {
        $levels = [];
        foreach ($this->collection as $category) {
            $level = $this->calculateLevel($category);
            $levels[$level] = true;
        }
        return count($levels);
    }

    /**
     * Calculer la profondeur d'une catégorie
     */
    private function calculateDepth($category): int
    {
        $depth = 0;
        $current = $category;
        while ($current->parent_id && $current->parent) {
            $depth++;
            $current = $current->parent;
        }
        return $depth;
    }

    /**
     * Calculer le niveau d'une catégorie
     */
    private function calculateLevel($category): int
    {
        return $this->calculateDepth($category) + 1;
    }
}
