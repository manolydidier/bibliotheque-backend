<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleRatingResource extends JsonResource
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
        $canModerate = $user && ($user->hasPermissionTo('ratings.moderate') || $isAdmin);
        $canManage = $user && ($user->hasPermissionTo('ratings.manage') || $isAdmin);
        $isAuthor = $user && $this->user_id === $user->id;

        return [
            // Identifiants
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'article_id' => $this->article_id,
            'user_id' => $this->user_id,

            // Évaluation
            'rating' => $this->rating,
            'rating_stars' => $this->rating_stars,
            'rating_percentage' => $this->rating_percentage,
            'review' => $this->review,
            'criteria_ratings' => $this->criteria_ratings,

            // Informations invité
            'guest_email' => $this->guest_email,
            'guest_name' => $this->guest_name,

            // Statuts et métadonnées
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_verified' => $this->is_verified,
            'is_helpful' => $this->is_helpful,

            // Compteurs
            'helpful_count' => $this->helpful_count,
            'not_helpful_count' => $this->not_helpful_count,

            // Relations
            'article' => $this->whenLoaded('article', function () {
                return new ArticleResource($this->article);
            }),
            'user' => $this->whenLoaded('user', function () {
                return new UserResource($this->user);
            }),
            'moderated_by' => $this->whenLoaded('moderatedBy', function () {
                return new UserResource($this->moderatedBy);
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
            'moderated_at' => $this->moderated_at?->toISOString(),

            // Informations de modération
            'moderation_notes' => $this->when($canModerate, $this->moderation_notes),
            'ip_address' => $this->when($canModerate, $this->ip_address),
            'user_agent' => $this->when($canModerate, $this->user_agent),

            // Liens HATEOAS
            '_links' => [
                'self' => [
                    'href' => route('api.ratings.show', $this->uuid),
                    'method' => 'GET',
                ],
                'edit' => $this->when($canManage || $isAuthor, [
                    'href' => route('api.ratings.update', $this->uuid),
                    'method' => 'PUT',
                ]),
                'delete' => $this->when($canManage || $isAuthor, [
                    'href' => route('api.ratings.destroy', $this->uuid),
                    'method' => 'DELETE',
                ]),
                'mark_helpful' => [
                    'href' => route('api.ratings.mark-helpful', $this->uuid),
                    'method' => 'POST',
                ],
                'mark_not_helpful' => [
                    'href' => route('api.ratings.mark-not-helpful', $this->uuid),
                    'method' => 'POST',
                ],
                'article' => [
                    'href' => route('api.articles.show', $this->article->slug),
                    'method' => 'GET',
                ],
                'user' => $this->when($this->user_id, [
                    'href' => route('api.users.show', $this->user->id),
                    'method' => 'GET',
                ]),
            ],

            // Actions de modération
            '_moderation' => $this->when($canModerate, [
                'approve' => [
                    'href' => route('api.ratings.approve', $this->uuid),
                    'method' => 'PATCH',
                ],
                'reject' => [
                    'href' => route('api.ratings.reject', $this->uuid),
                    'method' => 'PATCH',
                ],
                'verify' => [
                    'href' => route('api.ratings.verify', $this->uuid),
                    'method' => 'PATCH',
                ],
                'feature' => [
                    'href' => route('api.ratings.toggle-featured', $this->uuid),
                    'method' => 'PATCH',
                ],
            ]),

            // Actions disponibles
            '_actions' => [
                'can_edit' => $canManage || $isAuthor,
                'can_delete' => $canManage || $isAuthor,
                'can_mark_helpful' => $this->status === 'approved',
                'can_mark_not_helpful' => $this->status === 'approved',
                'can_moderate' => $canModerate,
                'can_verify' => $canModerate,
                'can_feature' => $canModerate,
            ],

            // Statistiques
            '_stats' => [
                'total_votes' => $this->helpful_count + $this->not_helpful_count,
                'helpful_percentage' => $this->total_votes > 0 
                    ? round(($this->helpful_count / $this->total_votes) * 100, 1)
                    : 0,
            ],
        ];
    }
}
