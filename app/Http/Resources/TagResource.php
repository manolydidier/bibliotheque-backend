<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
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
        $canManage = $user && ($user->hasPermissionTo('tags.manage') || $isAdmin);

        return [
            // Identifiants
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,

            // Informations de base
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'color' => $this->color,

            // Métadonnées
            'meta' => $this->meta,
            'usage_count' => $this->usage_count,

            // Statuts
            'is_active' => $this->is_active,

            // Relations
            'articles_count' => $this->when(isset($this->articles_count), $this->articles_count),

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
            '_links' => [
                'self' => [
                    'href' => route('api.tags.show', $this->uuid),
                    'method' => 'GET',
                ],
                'edit' => $this->when($canManage, [
                    'href' => route('api.tags.update', $this->uuid),
                    'method' => 'PUT',
                ]),
                'delete' => $this->when($canManage, [
                    'href' => route('api.tags.destroy', $this->uuid),
                    'method' => 'DELETE',
                ]),
                'toggle_active' => $this->when($canManage, [
                    'href' => route('api.tags.toggle-active', $this->uuid),
                    'method' => 'PATCH',
                ]),
                'articles' => [
                    'href' => route('api.tags.articles', $this->uuid),
                    'method' => 'GET',
                ],
                'related_tags' => [
                    'href' => route('api.tags.related', $this->uuid),
                    'method' => 'GET',
                ],
            ],

            // Actions disponibles
            '_actions' => $this->when($canManage, [
                'can_edit' => true,
                'can_delete' => $this->usage_count === 0,
                'can_toggle_active' => true,
                'can_merge' => $this->usage_count > 0,
            ]),
        ];
    }
}
