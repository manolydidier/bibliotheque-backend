<?php

namespace App\Jobs;

use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateArticleMeta implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Article $article)
    {
    }

    public function handle(): void
    {
        $meta = $this->article->meta ?? [];
        $meta['keywords'] = $meta['keywords'] ?? implode(', ', array_filter([
            $this->article->title,
        ]));
        $meta['description'] = $meta['description'] ?? str($this->article->excerpt ?? strip_tags($this->article->content))->limit(160)->toString();
        $this->article->update(['meta' => $meta]);
    }
}


