<?php

namespace App\Observers;

use App\Models\Article;
use App\Models\ArticleShare;
use App\Services\ArticleCountersService;

class ArticleShareObserver
{
    public function created(ArticleShare $share): void
    {
        if ($share->article) {
            app(ArticleCountersService::class)->incrementShare($share->article);
        }
    }

    public function updated(ArticleShare $share): void
    {
        // Déplacement de partage d’un article A vers B
        if ($share->wasChanged('article_id')) {
            if ($oldId = (int) $share->getOriginal('article_id')) {
                if ($old = Article::find($oldId)) {
                    app(ArticleCountersService::class)->decrementShare($old);
                }
            }
            if ($share->article) {
                app(ArticleCountersService::class)->incrementShare($share->article);
            }
        }
    }

    public function deleted(ArticleShare $share): void
    {
        if ($share->article) {
            app(ArticleCountersService::class)->decrementShare($share->article);
        }
    }

    public function restored(ArticleShare $share): void
    {
        if ($share->article) {
            app(ArticleCountersService::class)->incrementShare($share->article);
        }
    }
}
