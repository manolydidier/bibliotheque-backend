<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user && $user->hasRole(['admin', 'super_admin']);
        $canManage = $user && ($user->hasPermissionTo('categories.manage') || $isAdmin);

        return [
            // Identifiants
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,

            // Informations de base
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,

            // Métadonnées
            'meta' => $this->meta,
            'sort_order' => $this->sort_order,

            // Statuts
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,

            // Relations
            'parent' => $this->whenLoaded('parent', function () {
                return new CategoryResource($this->parent);
            }),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'articles_count' => $this->when(isset($this->articles_count), $this->articles_count),
            'children_count' => $this->when(isset($this->children_count), $this->children_count),

            // Audit
            'created_by' => $this->whenLoaded('createdBy', function () {
                return new UserResource($this->createdBy);
            }),
            'updated_by' => $this->whenLoaded('updatedBy', function () {
                return new UserResource($this->updatedBy);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at?->toISOString()),

            // Liens HATEOAS
            // '_links' => [
            //     'self' => [
            //         'href' => route('api.categories.show', $this->uuid),
            //         'method' => 'GET',
            //     ],
            //     'edit' => $this->when($canManage, [
            //         'href' => route('api.categories.update', $this->uuid),
            //         'method' => 'PUT',
            //     ]),
            //     'delete' => $this->when($canManage, [
            //         'href' => route('api.categories.destroy', $this->uuid),
            //         'method' => 'DELETE',
            //     ]),
            //     'toggle_active' => $this->when($canManage, [
            //         'href' => route('api.categories.toggle-active', $this->uuid),
            //         'method' => 'PATCH',
            //     ]),
            //     'toggle_featured' => $this->when($canManage, [
            //         'href' => route('api.categories.toggle-featured', $this->uuid),
            //         'method' => 'PATCH',
            //     ]),
            //     'articles' => [
            //         'href' => route('api.categories.articles', $this->uuid),
            //         'method' => 'GET',
            //     ],
            //     'children' => [
            //         'href' => route('api.categories.children', $this->uuid),
            //         'method' => 'GET',
            //     ],
            //     'parent' => $this->when($this->parent_id, [
            //         'href' => route('api.categories.show', $this->parent->uuid),
            //         'method' => 'GET',
            //     ]),
            // ],

            // Actions disponibles
            '_actions' => $this->when($canManage, [
                'can_edit' => true,
                'can_delete' => $this->articles_count === 0 && $this->children_count === 0,
                'can_toggle_active' => true,
                'can_toggle_featured' => true,
                'can_manage_children' => true,
            ]),
        ];
    }
}
