<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleRatingController extends Controller
{
    public function show(Request $request, Article $article)
    {
        $mine = $this->findMyRating($request, $article);

        $lastApproved = ArticleRating::query()
            ->where('article_id', $article->id)
            ->where('status', 'approved')
            ->latest()
            ->take(10)
            ->get(['id','uuid','rating','review','guest_name','user_id','created_at']);

        return response()->json([
            'data' => [
                'article_id'     => $article->id,
                'rating_average' => (float) $article->rating_average,
                'rating_count'   => (int)   $article->rating_count,
                'my_rating'      => $mine?->rating,
                'my_review'      => $mine?->review,
                'my_rating_uuid' => $mine?->uuid,
                'allow_rating'   => (bool) ($article->allow_rating ?? true),
                'recent_reviews' => $lastApproved,
            ],
        ]);
    }

    public function store(Request $request, Article $article)
    {
        if ($article->allow_rating === false) {
            abort(403, "La notation est désactivée pour cet article.");
        }

        $data = $request->validate([
            'rating'            => ['required','integer','min:1','max:5'],
            'review'            => ['nullable','string','max:5000'],
            'criteria_ratings'  => ['nullable','array'],
            'criteria_ratings.*'=> ['integer','min:1','max:5'],
            'guest_email'       => ['nullable','email','max:190'],
            'guest_name'        => ['nullable','string','max:190'],
        ]);

        $autoApprove = (bool) (config('content.ratings_auto_approve', true));
        $status = $autoApprove ? 'approved' : 'pending';

        DB::transaction(function () use ($request, $article, $data, $status) {
            $row = Article::whereKey($article->id)->lockForUpdate()->first();
            $existing = $this->findMyRating($request, $row, $data['guest_email'] ?? null);

            $payload = [
                'tenant_id'        => $row->tenant_id ?? null,
                'rating'           => (int) $data['rating'],
                'review'           => $data['review'] ?? null,
                'criteria_ratings' => $data['criteria_ratings'] ?? null,
                'status'           => $status,
                'ip_address'       => $request->ip(),
                'user_agent'       => (string) $request->userAgent(),
            ];

            if ($request->user()) {
                $payload['user_id'] = $request->user()->id;
                $payload['guest_email'] = null;
                $payload['guest_name']  = null;
            } else {
                $payload['guest_email'] = $data['guest_email'] ?? null;
                $payload['guest_name']  = $data['guest_name'] ?? null;
            }

            if ($existing) {
                $existing->fill($payload)->save();
            } else {
                $payload['article_id'] = $row->id;

                if (!$request->user() && empty($payload['guest_email'])) {
                    // Merge IP/UA < 24h
                    $recent = ArticleRating::query()
                        ->where('article_id', $row->id)
                        ->whereNull('user_id')
                        ->where('ip_address', $payload['ip_address'])
                        ->where('user_agent', $payload['user_agent'])
                        ->where('created_at', '>=', now()->subDay())
                        ->first();

                    if ($recent) {
                        $recent->fill($payload)->save();
                    } else {
                        // Le modèle mettra uuid tout seul (creating)
                        (new ArticleRating($payload))->save();
                    }
                } else {
                    $finder = ArticleRating::query()
                        ->where('article_id', $row->id)
                        ->where(function ($q) use ($payload) {
                            if (!empty($payload['user_id'])) {
                                $q->where('user_id', $payload['user_id']);
                            } else {
                                $q->whereNull('user_id')
                                  ->where('guest_email', $payload['guest_email']);
                            }
                        })
                        ->first();

                    if ($finder) {
                        $finder->fill($payload)->save();
                    } else {
                        (new ArticleRating($payload))->save();
                    }
                }
            }

            $row->updateRatingStats();
            $article->refresh();
        });

        $mine = $this->findMyRating($request, $article, $data['guest_email'] ?? null);

        return response()->json([
            'message' => $status === 'approved' ? 'Note enregistrée.' : 'Note enregistrée, en attente de modération.',
            'data' => [
                'article_id'     => $article->id,
                'rating_average' => (float) $article->rating_average,
                'rating_count'   => (int)   $article->rating_count,
                'my_rating'      => $mine?->rating,
                'my_review'      => $mine?->review,
                'my_rating_uuid' => $mine?->uuid,
                'status'         => $mine?->status,
            ],
        ], 201);
    }

    public function update(Request $request, Article $article)
    {
        return $this->store($request, $article);
    }

    public function destroy(Request $request, Article $article)
    {
        DB::transaction(function () use ($request, $article) {
            $row = Article::whereKey($article->id)->lockForUpdate()->first();
            $mine = $this->findMyRating($request, $row);
            if ($mine) {
                $mine->delete();
            }
            $row->updateRatingStats();
            $article->refresh();
        });

        return response()->json([
            'message' => 'Votre note a été retirée.',
            'data' => [
                'article_id'     => $article->id,
                'rating_average' => (float) $article->rating_average,
                'rating_count'   => (int)   $article->rating_count,
                'my_rating'      => null,
                'my_review'      => null,
                'my_rating_uuid' => null,
            ],
        ]);
    }

    public function voteHelpful(Request $request, Article $article, ArticleRating $rating)
    {
        // $rating est résolu par UUID grâce à getRouteKeyName()
        abort_unless($rating->article_id === $article->id, 404);

        $data = $request->validate(['helpful' => ['required','boolean']]);

        $data['helpful'] ? $rating->markAsHelpful() : $rating->markAsNotHelpful();

        return response()->json([
            'message' => 'Merci pour votre retour.',
            'data' => [
                'helpful_count'     => $rating->helpful_count,
                'not_helpful_count' => $rating->not_helpful_count,
                'is_helpful'        => $rating->is_helpful,
                'total_votes'       => $rating->getTotalVotes(),
                'uuid'              => $rating->uuid,
            ],
        ]);
    }

    private function findMyRating(Request $request, Article $article, ?string $guestEmail = null): ?ArticleRating
    {
        if ($request->user()) {
            return ArticleRating::where('article_id', $article->id)
                ->where('user_id', $request->user()->id)
                ->latest()->first();
        }

        if ($guestEmail) {
            return ArticleRating::where('article_id', $article->id)
                ->whereNull('user_id')
                ->where('guest_email', $guestEmail)
                ->latest()->first();
        }

        return ArticleRating::where('article_id', $article->id)
            ->whereNull('user_id')
            ->where('ip_address', $request->ip())
            ->where('user_agent', (string) $request->userAgent())
            ->where('created_at', '>=', now()->subDay())
            ->latest()->first();
    }
}
