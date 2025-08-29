<?php

namespace App\Listeners;

use App\Events\ArticleCreated;
use App\Events\ArticleDeleted;
use App\Events\ArticlePublished;
use App\Events\ArticleUpdated;
use App\Models\ArticleHistory;

class RecordArticleHistory
{
    public function handle($event): void
    {
        $article = $event->article;
        $user = $event->actor;
        $action = match(true) {
            $event instanceof ArticleCreated => 'create',
            $event instanceof ArticleUpdated => 'update',
            $event instanceof ArticlePublished => 'publish',
            $event instanceof ArticleDeleted => 'delete',
            default => 'update',
        };

        ArticleHistory::create([
            'tenant_id' => $article->tenant_id,
            'article_id' => $article->id,
            'user_id' => $user?->id,
            'action' => $action,
            'changes' => property_exists($event, 'changes') ? $event->changes : null,
            'previous_values' => null,
            'new_values' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}


