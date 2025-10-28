<?php
// app/Http/Controllers/ArticleViewController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\ArticleCountersService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleViewController extends Controller
{
    public function __construct(private ArticleCountersService $counters) {}

    public function store(Request $request, Article $article)
    {
        $data = $request->validate([
            'delta'       => 'sometimes|integer|min:1|max:100',
            'dedupe_key'  => 'nullable|string|max:255',
            'ttl_seconds' => 'sometimes|integer|min:30|max:3600',
        ]);

        $delta = $data['delta'] ?? 1;
        $ttl   = $data['ttl_seconds'] ?? (int) config('analytics.view_ttl', 300);

        $finger = $data['dedupe_key']
            ?? sprintf('ip:%s|ua:%s', $request->ip(), substr($request->userAgent() ?? '', 0, 160));

        $incremented = $this->counters->incrementView($article, $delta, $finger, $ttl);

        // (optionnel) total du jour retourné pour l’UI
        $today = now()->toDateString();
        $tenantId = ($article->tenant_id ?? 0);
        $todayCount = DB::table('article_views')
            ->where('tenant_id', $tenantId ?: 0)
            ->where('article_id', $article->id)
            ->where('date', $today)
            ->value('count') ?? 0;

        return response()->json([
            'ok'          => true,
            'incremented' => (bool) $incremented,
            'today_count' => (int) $todayCount,
        ]);
    }
}
