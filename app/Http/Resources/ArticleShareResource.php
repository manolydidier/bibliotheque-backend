<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleShareResource extends JsonResource
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
        $canManage = $user && ($user->hasPermissionTo('shares.manage') || $isAdmin);

        return [
            // Identifiants
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'article_id' => $this->article_id,
            'user_id' => $this->user_id,

            // Méthode et plateforme
            'method' => $this->method,
            'method_label' => $this->method_label,
            'platform' => $this->platform,
            'platform_label' => $this->platform_label,

            // URLs et métadonnées
            'url' => $this->url,
            'meta' => $this->meta,

            // Suivi et géolocalisation
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'referrer' => $this->referrer,
            'location' => $this->location,

            // Conversion
            'is_converted' => $this->is_converted,
            'converted_at' => $this->converted_at?->toISOString(),

            // Relations
            'article' => $this->whenLoaded('article', function () {
                return new ArticleResource($this->article);
            }),
            'user' => $this->whenLoaded('user', function () {
                return new UserResource($this->user);
            }),

            // Audit
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Liens HATEOAS
            '_links' => [
                'self' => [
                    'href' => route('api.shares.show', $this->uuid),
                    'method' => 'GET',
                ],
                'edit' => $this->when($canManage, [
                    'href' => route('api.shares.update', $this->uuid),
                    'method' => 'PUT',
                ]),
                'delete' => $this->when($canManage, [
                    'href' => route('api.shares.destroy', $this->uuid),
                    'method' => 'DELETE',
                ]),
                'mark_converted' => $this->when($canManage, [
                    'href' => route('api.shares.mark-converted', $this->uuid),
                    'method' => 'PATCH',
                ]),
                'article' => [
                    'href' => route('api.articles.show', $this->article->slug),
                    'method' => 'GET',
                ],
                'user' => $this->when($this->user_id, [
                    'href' => route('api.users.show', $this->user->id),
                    'method' => 'GET',
                ]),
                'share_url' => [
                    'href' => $this->share_url,
                    'method' => 'GET',
                ],
            ],

            // Actions disponibles
            '_actions' => $this->when($canManage, [
                'can_edit' => true,
                'can_delete' => true,
                'can_mark_converted' => true,
                'can_track_analytics' => true,
            ]),

            // Statistiques
            '_stats' => [
                'time_since_share' => $this->created_at ? $this->created_at->diffForHumans() : null,
                'time_to_conversion' => $this->converted_at && $this->created_at 
                    ? $this->created_at->diffInSeconds($this->converted_at)
                    : null,
            ],

            // Informations techniques
            '_technical' => $this->when($canManage, [
                'user_agent_parsed' => $this->user_agent_parsed ?? null,
                'location_formatted' => $this->location_formatted ?? null,
                'referrer_domain' => $this->referrer_domain ?? null,
            ]),
        ];
    }
}
