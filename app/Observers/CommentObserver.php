<?php

namespace App\Observers;

use App\Models\Article;
use App\Models\Comment;
use App\Services\ArticleCountersService;

class CommentObserver
{
    public function created(Comment $comment): void
    {
        if ($comment->status === 'approved' && $comment->article) {
            app(ArticleCountersService::class)->incrementApproved($comment->article);
        }
    }

    public function updated(Comment $comment): void
    {
        $svc = app(ArticleCountersService::class);

        if ($comment->wasChanged('status')) {
            $old = $comment->getOriginal('status');
            $new = $comment->status;

            if ($old !== 'approved' && $new === 'approved' && $comment->article) {
                $svc->incrementApproved($comment->article);
            } elseif ($old === 'approved' && $new !== 'approved' && $comment->article) {
                $svc->decrementApproved($comment->article);
            }
        }

        if ($comment->wasChanged('article_id') && $comment->status === 'approved') {
            if ($oldId = $comment->getOriginal('article_id')) {
                if ($old = Article::find($oldId)) app(ArticleCountersService::class)->decrementApproved($old);
            }
            if ($comment->article) $svc->incrementApproved($comment->article);
        }
    }

    public function deleted(Comment $comment): void
    {
        if ($comment->status === 'approved' && $comment->article) {
            app(ArticleCountersService::class)->decrementApproved($comment->article);
        }
    }

    public function restored(Comment $comment): void
    {
        if ($comment->status === 'approved' && $comment->article) {
            app(ArticleCountersService::class)->incrementApproved($comment->article);
        }
    }
}
