<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleMediaResource extends JsonResource
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
        $canManage = $user && ($user->hasPermissionTo('media.manage') || $isAdmin);

        return [
            // Identifiants
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'article_id' => $this->article_id,

            // Informations de base
            'name' => $this->name,
            'filename' => $this->filename,
            'path' => $this->path,
            'url' => $this->url,
            'thumbnail_path' => $this->thumbnail_path,
            'thumbnail_url' => $this->thumbnail_url,

            // Type et métadonnées
            'type' => $this->type,
            'type_label' => $this->type_label,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_formatted' => $this->size_formatted,
            'dimensions' => $this->dimensions,
            'meta' => $this->meta,

            // SEO et accessibilité
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,

            // Organisation
            'sort_order' => $this->sort_order,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,

            // Relations
            'article' => $this->whenLoaded('article', function () {
                return new ArticleResource($this->article);
            }),

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
                    'href' => route('api.media.show', $this->uuid),
                    'method' => 'GET',
                ],
                'edit' => $this->when($canManage, [
                    'href' => route('api.media.update', $this->uuid),
                    'method' => 'PUT',
                ]),
                'delete' => $this->when($canManage, [
                    'href' => route('api.media.destroy', $this->uuid),
                    'method' => 'DELETE',
                ]),
                'toggle_featured' => $this->when($canManage, [
                    'href' => route('api.media.toggle-featured', $this->uuid),
                    'method' => 'PATCH',
                ]),
                'download' => [
                    'href' => route('api.media.download', $this->uuid),
                    'method' => 'GET',
                ],
                'preview' => [
                    'href' => route('api.media.preview', $this->uuid),
                    'method' => 'GET',
                ],
                'article' => [
                    'href' => route('api.articles.show', $this->article->slug),
                    'method' => 'GET',
                ],
                'thumbnail' => $this->when($this->thumbnail_path, [
                    'href' => $this->thumbnail_url,
                    'method' => 'GET',
                ]),
            ],

            // Actions disponibles
            '_actions' => $this->when($canManage, [
                'can_edit' => true,
                'can_delete' => true,
                'can_toggle_featured' => true,
                'can_reorder' => true,
                'can_replace' => true,
            ]),

            // Informations techniques
            '_technical' => $this->when($canManage, [
                'file_exists' => $this->file_exists,
                'thumbnail_exists' => $this->thumbnail_exists,
                'storage_disk' => config('filesystems.default'),
                'processing_status' => $this->processing_status ?? 'completed',
            ]),
        ];
    }
}
