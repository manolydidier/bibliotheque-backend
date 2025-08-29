<?php

namespace App\Observers;

use App\Events\ArticleCreated;
use App\Events\ArticleUpdated;
use App\Jobs\GenerateArticleMeta;
use App\Jobs\RecalculateArticleRating;
use App\Models\Article;
use Illuminate\Support\Facades\Auth;

class ArticleObserver
{
    public function created(Article $article): void
    {
        event(new ArticleCreated($article, Auth::user()));
        dispatch(new GenerateArticleMeta($article));
    }

    public function updated(Article $article): void
    {
        event(new ArticleUpdated($article, Auth::user(), $article->getChanges()));
    }

    public function deleted(Article $article): void
    {
        // history handled by listener via event on service
    }
}


