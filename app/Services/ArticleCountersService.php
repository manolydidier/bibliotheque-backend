<?php

namespace App\Services;

use App\Models\Article;
use Carbon\Carbon;
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
     public function incrementView(Article $article, int $delta = 1, ?string $dedupeKey = null, int $ttlSeconds = 300): bool
    {
        if ($dedupeKey) {
            $cacheKey = 'viewed:article:' . $article->id . ':' . sha1($dedupeKey);
            if (!Cache::add($cacheKey, 1, $ttlSeconds)) {
                return false;
            }
        }

        $today = Carbon::today()->toDateString();
        $tenantId = property_exists($article, 'tenant_id')
            ? ($article->tenant_id ?: 0)
            : (method_exists($article, 'getAttribute') ? ($article->getAttribute('tenant_id') ?: 0) : 0);

        DB::transaction(function () use ($article, $tenantId, $today, $delta) {
            $q = DB::table('article_views')
                ->where('article_id', $article->id)
                ->where('tenant_id', $tenantId)
                ->where('date', $today);

            $updated = $q->increment('count', $delta);

            if ($updated === 0) {
                try {
                    DB::table('article_views')->insert([
                        'article_id' => $article->id,
                        'tenant_id'  => $tenantId,
                        'date'       => $today,
                        'count'      => $delta,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    $q->increment('count', $delta);
                }
            }
        });

        return true;
    }

    /** --------- Helpers --------- */
    private function bustArticleCache(Article $article): void
    {
        Cache::forget("article_{$article->id}");
        Cache::forget("article_{$article->slug}");
    }

    
}
