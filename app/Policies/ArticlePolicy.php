<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    /**
     * Admin shortcut: si true => autorisé; null => continue.
     */
    public function before(?User $user, string $ability): ?bool
    {
        if ($user && $this->isAdmin($user)) {
            return true;
        }
        return null;
    }

    public function viewAny(?User $user): bool
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

        if ($this->isAuthor($user, $article)) {
            return true;
        }

        return $this->can($user, 'articles.view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'articles.create');
    }

    public function update(User $user, Article $article): bool
    {
        if ($this->isAuthor($user, $article)) {
            return true;
        }
        return $this->can($user, 'articles.update');
    }

    public function delete(User $user, Article $article): bool
    {
        if ($this->isAuthor($user, $article)) {
            // autoriser la suppression de son propre article (adapter selon ta règle)
            return true;
        }
        return $this->can($user, 'articles.delete');
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
        if ($this->isAuthor($user, $article)) {
            return $this->can($user, 'articles.publish_own') || $this->can($user, 'articles.publish');
        }
        return $this->can($user, 'articles.publish');
    }

    public function viewStats(User $user, Article $article): bool
    {
        if ($this->isAuthor($user, $article)) {
            return true;
        }
        return $this->can($user, 'articles.stats.view');
    }

    public function viewFullContent(?User $user, Article $article): bool
    {
        if ($article->isPublic()) {
            return true;
        }
        if (!$user) {
            return false;
        }
        if ($this->isAuthor($user, $article)) {
            return true;
        }
        return $this->can($user, 'articles.view_full');
    }

    /* -------------------- Helpers -------------------- */

    private function isAuthor(User $user, Article $article): bool
    {
        return (int) $article->author_id === (int) $user->id;
    }

    private function isAdmin(User $user): bool
    {
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin');
        }
        return $user->roles()->where('is_admin', true)->exists();
    }

    private function can(User $user, string $permission): bool
    {
        if (method_exists($user, 'canAccess')) {
            return $user->canAccess($permission);
        }
        return method_exists($user, 'can') ? $user->can($permission) : false;
    }
}
