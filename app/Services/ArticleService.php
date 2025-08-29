<?php

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use App\Events\ArticleCreated;
use App\Events\ArticleUpdated;
use App\Events\ArticlePublished;
use App\Events\ArticleDeleted;

class ArticleService
{
    public function createArticle(array $data, User $actor): Article
    {
        $article = new Article();
        $this->fillArticle($article, $data, $actor, true);
        $article->save();

        $this->syncRelations($article, $data);

        Cache::forget('articles_list');

        event(new ArticleCreated($article, $actor));

        return $article->fresh();
    }

    public function updateArticle(Article $article, array $data, User $actor): Article
    {
        $this->fillArticle($article, $data, $actor, false);
        $article->save();

        $this->syncRelations($article, $data);

        Cache::forget('articles_list');

        event(new ArticleUpdated($article, $actor, $article->getChanges()));

        return $article->fresh();
    }

    public function deleteArticle(Article $article): void
    {
        $article->delete();
        Cache::forget('articles_list');
        event(new ArticleDeleted($article));
    }

    public function publishArticle(Article $article, User $actor): Article
    {
        $article->status = ArticleStatus::PUBLISHED;
        $article->published_at = now();
        $article->reviewed_by = $actor->id;
        $article->reviewed_at = now();
        $article->save();

        event(new ArticlePublished($article, $actor));

        return $article->fresh();
    }

    public function unpublishArticle(Article $article, User $actor): Article
    {
        $article->status = ArticleStatus::DRAFT;
        $article->save();
        return $article->fresh();
    }

    public function duplicateArticle(Article $source, User $actor): Article
    {
        $duplicate = $source->replicate([
            'slug', 'published_at', 'reviewed_at', 'reviewed_by', 'view_count', 'share_count', 'comment_count', 'rating_average', 'rating_count'
        ]);
        $duplicate->title = $source->title . ' (copie)';
        $duplicate->slug = null;
        $duplicate->status = ArticleStatus::DRAFT;
        $duplicate->created_by = $actor->id;
        $duplicate->updated_by = $actor->id;
        $duplicate->published_at = null;
        $duplicate->save();

        // Relations
        $duplicate->categories()->sync($source->categories->mapWithKeys(function ($cat) {
            return [$cat->id => ['is_primary' => $cat->pivot->is_primary, 'sort_order' => $cat->pivot->sort_order]];
        })->toArray());

        $duplicate->tags()->sync($source->tags->mapWithKeys(function ($tag) {
            return [$tag->id => ['sort_order' => $tag->pivot->sort_order]];
        })->toArray());

        return $duplicate->fresh();
    }

    public function getArticleStats(Article $article): array
    {
        return [
            'views' => $article->view_count,
            'shares' => $article->share_count,
            'comments' => $article->comment_count,
            'rating_average' => $article->rating_average,
            'rating_count' => $article->rating_count,
            'is_published' => $article->isPublished(),
            'is_scheduled' => $article->isScheduled(),
            'is_expired' => $article->isExpired(),
        ];
    }

    private function fillArticle(Article $article, array $data, User $actor, bool $isCreate): void
    {
        $fields = [
            'tenant_id', 'title', 'slug', 'excerpt', 'content', 'featured_image', 'featured_image_alt', 'meta', 'seo_data',
            'status', 'visibility', 'password', 'published_at', 'scheduled_at', 'expires_at',
            'is_featured', 'is_sticky', 'allow_comments', 'allow_sharing', 'allow_rating',
            'author_name', 'author_bio', 'author_avatar', 'author_id',
        ];

        $article->fill(Arr::only($data, $fields));

        if ($isCreate) {
            $article->created_by = $actor->id;
        }
        $article->updated_by = $actor->id;
    }

    private function syncRelations(Article $article, array $data): void
    {
        if (isset($data['categories']) && is_array($data['categories'])) {
            $sync = [];
            foreach ($data['categories'] as $cat) {
                if (isset($cat['id'])) {
                    $sync[(int) $cat['id']] = [
                        'is_primary' => (bool) ($cat['is_primary'] ?? false),
                        'sort_order' => (int) ($cat['sort_order'] ?? 0),
                    ];
                }
            }
            $article->categories()->sync($sync);
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $sync = [];
            foreach ($data['tags'] as $tag) {
                if (isset($tag['id'])) {
                    $sync[(int) $tag['id']] = [
                        'sort_order' => (int) ($tag['sort_order'] ?? 0),
                    ];
                }
            }
            $article->tags()->sync($sync);
        }
    }
}


