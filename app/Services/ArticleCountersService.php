<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ArticleCountersService
{
    /** --------- Comments --------- */
    public function incrementApproved(Article $article, int $by = 1): void
    {
        if ($by === 0) return;

        DB::table($article->getTable())
            ->where($article->getKeyName(), $article->getKey()) // ✅ fix
            ->update([
                'comment_count' => DB::raw('GREATEST(comment_count + '.(int)$by.', 0)')
            ]);

        $this->bustArticleCache($article);
    }

    public function decrementApproved(Article $article, int $by = 1): void
    {
        $this->incrementApproved($article, -$by);
    }

    /** --------- Shares --------- */
    public function incrementShare(Article $article, int $by = 1): void
    {
        if ($by === 0) return;

        DB::table($article->getTable())
            ->where($article->getKeyName(), $article->getKey()) // ✅ fix
            ->update([
                'share_count' => DB::raw('GREATEST(share_count + '.(int)$by.', 0)')
            ]);

        $this->bustArticleCache($article);
    }

    public function decrementShare(Article $article, int $by = 1): void
    {
        $this->incrementShare($article, -$by);
    }

    /**
     * --------- Views (avec anti-double comptage optionnel) ---------
     * @return bool true si incrémenté, false si dédupliqué
     */
    public function incrementView(Article $article, int $by = 1, ?string $dedupeKey = null, int $ttlSeconds = 300): bool
    {
        if ($by === 0) return false;

        if ($dedupeKey) {
            $cacheKey = "viewdedupe:{$article->getKey()}:{$dedupeKey}";
            if (!Cache::add($cacheKey, 1, now()->addSeconds($ttlSeconds))) {
                return false; // déjà compté récemment
            }
        }

        DB::table($article->getTable())
            ->where($article->getKeyName(), $article->getKey()) // ✅ fix
            ->update([
                'view_count' => DB::raw('GREATEST(view_count + '.(int)$by.', 0)')
            ]);

        $this->bustArticleCache($article);
        return true;
    }

    /** --------- Helpers --------- */
    private function bustArticleCache(Article $article): void
    {
        Cache::forget("article_{$article->id}");
        Cache::forget("article_{$article->slug}");
    }
}
