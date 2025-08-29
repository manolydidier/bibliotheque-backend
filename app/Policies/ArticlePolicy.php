<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(?User $user, Article $article): bool
    {
        if ($article->isPublic()) {
            return true;
        }

        if (!$user) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if ($this->isAuthor($user, $article)) {
            return true;
        }

        return $user->canAccess('articles.view');
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->canAccess('articles.create');
    }

    public function update(User $user, Article $article): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        if ($this->isAuthor($user, $article)) {
            return true;
        }
        return $user->canAccess('articles.update');
    }

    public function delete(User $user, Article $article): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        if ($this->isAuthor($user, $article)) {
            return true;
        }
        return $user->canAccess('articles.delete');
    }

    public function restore(User $user, Article $article): bool
    {
        return $this->delete($user, $article);
    }

    public function forceDelete(User $user, Article $article): bool
    {
        return $this->delete($user, $article);
    }

    public function publish(User $user, Article $article): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        if ($this->isAuthor($user, $article)) {
            return $user->canAccess('articles.publish_own') || $user->canAccess('articles.publish');
        }
        return $user->canAccess('articles.publish');
    }

    public function viewStats(User $user, Article $article): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        if ($this->isAuthor($user, $article)) {
            return true;
        }
        return $user->canAccess('articles.stats.view');
    }

    public function viewFullContent(User $user, Article $article): bool
    {
        if ($article->isPublic()) {
            return true;
        }
        if ($this->isAdmin($user)) {
            return true;
        }
        if ($this->isAuthor($user, $article)) {
            return true;
        }
        return $user->canAccess('articles.view_full');
    }

    private function isAuthor(User $user, Article $article): bool
    {
        return (int) $article->author_id === (int) $user->id;
    }

    private function isAdmin(User $user): bool
    {
        return $user->roles()->where('is_admin', true)->exists();
    }
}


