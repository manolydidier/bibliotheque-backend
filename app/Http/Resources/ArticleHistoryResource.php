<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleHistoryResource extends JsonResource
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
        $canViewHistory = $user && ($user->hasPermissionTo('articles.view_history') || $isAdmin);

        return [
            // Identifiants
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'article_id' => $this->article_id,
            'user_id' => $this->user_id,

            // Action et changements
            'action' => $this->action,
            'action_label' => $this->action_label,
            'changes' => $this->changes,
            'previous_values' => $this->previous_values,
            'new_values' => $this->new_values,
            'notes' => $this->notes,

            // Métadonnées
            'meta' => $this->meta,

            // Informations de suivi
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,

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
                    'href' => route('api.history.show', $this->uuid),
                    'method' => 'GET',
                ],
                'article' => [
                    'href' => route('api.articles.show', $this->article->slug),
                    'method' => 'GET',
                ],
                'user' => $this->when($this->user_id, [
                    'href' => route('api.users.show', $this->user->id),
                    'method' => 'GET',
                ]),
                'article_history' => [
                    'href' => route('api.articles.history', $this->article->slug),
                    'method' => 'GET',
                ],
            ],

            // Actions disponibles
            '_actions' => [
                'can_view' => $canViewHistory,
                'can_restore' => $canViewHistory && $this->action === 'deleted',
                'can_compare' => $canViewHistory && !empty($this->changes),
            ],

            // Résumé des changements
            '_changes_summary' => $this->when(!empty($this->changes), [
                'fields_changed' => array_keys($this->changes ?? []),
                'change_count' => count($this->changes ?? []),
                'has_content_changes' => isset($this->changes['content']),
                'has_metadata_changes' => isset($this->changes['meta']) || isset($this->changes['seo_data']),
                'has_status_changes' => isset($this->changes['status']) || isset($this->changes['visibility']),
            ]),

            // Informations techniques
            '_technical' => $this->when($canViewHistory, [
                'user_agent_parsed' => $this->user_agent_parsed ?? null,
                'ip_location' => $this->ip_location ?? null,
                'session_id' => $this->session_id ?? null,
            ]),
        ];
    }
}
