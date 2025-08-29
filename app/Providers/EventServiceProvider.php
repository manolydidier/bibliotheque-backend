<?php

namespace App\Providers;

use App\Events\ArticleCreated;
use App\Events\ArticleDeleted;
use App\Events\ArticlePublished;
use App\Events\ArticleUpdated;
use App\Listeners\RecordArticleHistory;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ArticleCreated::class => [RecordArticleHistory::class],
        ArticleUpdated::class => [RecordArticleHistory::class],
        ArticlePublished::class => [RecordArticleHistory::class],
        ArticleDeleted::class => [RecordArticleHistory::class],
    ];

    public function boot(): void
    {
        //
    }
}


