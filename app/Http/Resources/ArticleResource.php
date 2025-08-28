<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->when($request->user()?->can('viewFullContent', $this->resource), $this->content),
            'featured_image' => $this->featured_image,
            'featured_image_url' => $this->getFeaturedImageUrl(),
            'featured_image_alt' => $this->featured_image_alt,
            'meta' => $this->when($this->meta, $this->meta),
            'seo_data' => $this->when($this->seo_data, $this->seo_data),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'visibility' => [
                'value' => $this->visibility->value,
                'label' => $this->visibility->label(),
            ],
            'is_password_protected' => $this->isPasswordProtected(),
            'published_at' => $this->when($this->published_at, $this->published_at?->toISOString()),
            'scheduled_at' => $this->when($this->scheduled_at, $this->scheduled_at?->toISOString()),
            'expires_at' => $this->when($this->expires_at, $this->expires_at?->toISOString()),
            'reading_time' => $this->reading_time,
            'word_count' => $this->word_count,
            'view_count' => $this->view_count,
            'share_count' => $this->share_count,
            'comment_count' => $this->comment_count,
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            'is_featured' => $this->is_featured,
            'is_sticky' => $this->is_sticky,
            'allow_comments' => $this->allow_comments,
            'allow_sharing' => $this->allow_sharing,
            'allow_rating' => $this->allow_rating,
            'author' => $this->when($this->author, [
                'id' => $this->author?->id,
                'name' => $this->getAuthorDisplayName(),
                'avatar' => $this->getAuthorAvatarUrl(),
                'bio' => $this->author_bio,
            ]),
            'author_id' => $this->author_id,
            'author_name' => $this->author_name,
            'author_bio' => $this->author_bio,
            'author_avatar' => $this->author_avatar,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'media' => ArticleMediaResource::collection($this->whenLoaded('media')),
            'comments' => CommentResource::collection($this->whenLoaded('approvedComments')),
            'primary_category' => $this->when($this->relationLoaded('categories'), function () {
                return $this->getPrimaryCategory() ? new CategoryResource($this->getPrimaryCategory()) : null;
            }),
            'url' => $this->getUrl(),
            'can_edit' => $this->when($request->user(), function () use ($request) {
                return $request->user()->can('update', $this->resource);
            }),
            'can_delete' => $this->when($request->user(), function () use ($request) {
                return $request->user()->can('delete', $this->resource);
            }),
            'can_publish' => $this->when($request->user(), function () use ($request) {
                return $request->user()->can('publish', $this->resource);
            }),
            'is_published' => $this->isPublished(),
            'is_scheduled' => $this->isScheduled(),
            'is_expired' => $this->isExpired(),
            'is_public' => $this->isPublic(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'created_by' => $this->when($this->relationLoaded('createdBy'), function () {
                return $this->createdBy ? [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ] : null;
            }),
            'updated_by' => $this->when($this->relationLoaded('updatedBy'), function () {
                return $this->updatedBy ? [
                    'id' => $this->updatedBy->id,
                    'name' => $this->updatedBy->name,
                ] : null;
            }),
            'reviewed_by' => $this->when($this->reviewed_by, function () {
                return $this->reviewedBy ? [
                    'id' => $this->reviewedBy->id,
                    'name' => $this->reviewedBy->name,
                ] : null;
            }),
            'reviewed_at' => $this->when($this->reviewed_at, $this->reviewed_at?->toISOString()),
            'review_notes' => $this->when($this->review_notes, $this->review_notes),
            'tenant_id' => $this->when($this->tenant_id, $this->tenant_id),
            
            // Computed fields
            'formatted_published_at' => $this->when($this->published_at, function () {
                return $this->published_at->diffForHumans();
            }),
            'formatted_reading_time' => $this->when($this->reading_time, function () {
                return $this->reading_time . ' min';
            }),
            'formatted_word_count' => $this->when($this->word_count, function () {
                return number_format($this->word_count) . ' mots';
            }),
            'formatted_view_count' => $this->when($this->view_count, function () {
                return number_format($this->view_count);
            }),
            'formatted_share_count' => $this->when($this->share_count, function () {
                return number_format($this->share_count);
            }),
            'formatted_comment_count' => $this->when($this->comment_count, function () {
                return number_format($this->comment_count);
            }),
            'formatted_rating' => $this->when($this->rating_average > 0, function () {
                return number_format($this->rating_average, 1) . '/5';
            }),
            
            // SEO fields
            'meta_title' => $this->when($this->meta?->get('meta_title'), $this->meta?->get('meta_title')),
            'meta_description' => $this->when($this->meta?->get('meta_description'), $this->meta?->get('meta_description')),
            'meta_keywords' => $this->when($this->meta?->get('meta_keywords'), $this->meta?->get('meta_keywords')),
            
            // Social media fields
            'og_title' => $this->when($this->seo_data?->get('og_title'), $this->seo_data?->get('og_title')),
            'og_description' => $this->when($this->seo_data?->get('og_description'), $this->seo_data?->get('og_description')),
            'og_image' => $this->when($this->seo_data?->get('og_image'), $this->seo_data?->get('og_image')),
            'twitter_title' => $this->when($this->seo_data?->get('twitter_title'), $this->seo_data?->get('twitter_title')),
            'twitter_description' => $this->when($this->seo_data?->get('twitter_description'), $this->seo_data?->get('twitter_description')),
            'twitter_image' => $this->when($this->seo_data?->get('twitter_image'), $this->seo_data?->get('twitter_image')),
            
            // Schema.org structured data
            'schema_org' => $this->when($this->seo_data?->get('schema_org'), $this->seo_data?->get('schema_org')),
            
            // Links for HATEOAS
            '_links' => [
                'self' => [
                    'href' => route('api.articles.show', $this->slug),
                    'method' => 'GET',
                ],
                'edit' => $this->when($request->user()?->can('update', $this->resource), [
                    'href' => route('api.articles.update', $this->id),
                    'method' => 'PUT',
                ]),
                'delete' => $this->when($request->user()?->can('delete', $this->resource), [
                    'href' => route('api.articles.destroy', $this->id),
                    'method' => 'DELETE',
                ]),
                'publish' => $this->when($request->user()?->can('publish', $this->resource), [
                    'href' => route('api.articles.publish', $this->id),
                    'method' => 'POST',
                ]),
                'unpublish' => $this->when($request->user()?->can('publish', $this->resource), [
                    'href' => route('api.articles.unpublish', $this->id),
                    'method' => 'POST',
                ]),
                'duplicate' => $this->when($request->user()?->can('create', Article::class), [
                    'href' => route('api.articles.duplicate', $this->id),
                    'method' => 'POST',
                ]),
                'toggle_featured' => $this->when($request->user()?->can('update', $this->resource), [
                    'href' => route('api.articles.toggle-featured', $this->id),
                    'method' => 'POST',
                ]),
                'stats' => $this->when($request->user()?->can('viewStats', $this->resource), [
                    'href' => route('api.articles.stats', $this->id),
                    'method' => 'GET',
                ]),
                'comments' => [
                    'href' => route('api.articles.comments.index', $this->id),
                    'method' => 'GET',
                ],
                'ratings' => [
                    'href' => route('api.articles.ratings.index', $this->id),
                    'method' => 'GET',
                ],
                'shares' => [
                    'href' => route('api.articles.shares.index', $this->id),
                    'method' => 'GET',
                ],
                'media' => [
                    'href' => route('api.articles.media.index', $this->id),
                    'method' => 'GET',
                ],
            ],
        ];
    }
}
