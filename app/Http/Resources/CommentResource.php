<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
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
        $canModerate = $user && ($user->hasPermissionTo('comments.moderate') || $isAdmin);
        $canManage = $user && ($user->hasPermissionTo('comments.manage') || $isAdmin);
        $isAuthor = $user && $this->user_id === $user->id;

        return [
            // Identifiants
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'article_id' => $this->article_id,
            'parent_id' => $this->parent_id,
            'user_id' => $this->user_id,

            // Contenu
            'content' => $this->content,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,

            // Statuts et métadonnées
            'status' => $this->status,
            'status_label' => $this->status_label,
            'meta' => $this->meta,

            // Compteurs
            'like_count' => $this->like_count,
            'dislike_count' => $this->dislike_count,
            'reply_count' => $this->reply_count,

            // Flags
            'is_featured' => $this->is_featured,
            'is_verified' => $this->is_verified,

            // Relations
            'article' => $this->whenLoaded('article', function () {
                return new ArticleResource($this->article);
            }),
            'parent' => $this->whenLoaded('parent', function () {
                return new CommentResource($this->parent);
            }),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
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
                    'href' => route('api.comments.show', $this->uuid),
                    'method' => 'GET',
                ],
                'edit' => $this->when($canManage || $isAuthor, [
                    'href' => route('api.comments.update', $this->uuid),
                    'method' => 'PUT',
                ]),
                'delete' => $this->when($canManage || $isAuthor, [
                    'href' => route('api.comments.destroy', $this->uuid),
                    'method' => 'DELETE',
                ]),
                'reply' => [
                    'href' => route('api.comments.reply', $this->uuid),
                    'method' => 'POST',
                ],
                'like' => [
                    'href' => route('api.comments.like', $this->uuid),
                    'method' => 'POST',
                ],
                'dislike' => [
                    'href' => route('api.comments.dislike', $this->uuid),
                    'method' => 'POST',
                ],
                'article' => [
                    'href' => route('api.articles.show', $this->article->slug),
                    'method' => 'GET',
                ],
                'parent' => $this->when($this->parent_id, [
                    'href' => route('api.comments.show', $this->parent->uuid),
                    'method' => 'GET',
                ]),
                'replies' => [
                    'href' => route('api.comments.replies', $this->uuid),
                    'method' => 'GET',
                ],
            ],

            // Actions de modération
            '_moderation' => $this->when($canModerate, [
                'approve' => [
                    'href' => route('api.comments.approve', $this->uuid),
                    'method' => 'PATCH',
                ],
                'reject' => [
                    'href' => route('api.comments.reject', $this->uuid),
                    'method' => 'PATCH',
                ],
                'mark_spam' => [
                    'href' => route('api.comments.mark-spam', $this->uuid),
                    'method' => 'PATCH',
                ],
                'feature' => [
                    'href' => route('api.comments.toggle-featured', $this->uuid),
                    'method' => 'PATCH',
                ],
            ]),

            // Actions disponibles
            '_actions' => [
                'can_edit' => $canManage || $isAuthor,
                'can_delete' => $canManage || $isAuthor,
                'can_reply' => $this->status === 'approved',
                'can_like' => $this->status === 'approved',
                'can_dislike' => $this->status === 'approved',
                'can_moderate' => $canModerate,
                'can_feature' => $canModerate,
            ],
        ];
    }
}
