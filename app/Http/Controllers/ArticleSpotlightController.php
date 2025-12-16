<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\JsonResponse;

class ArticleSpotlightController extends Controller
{
    /**
     * Retourne :
     * - sticky   : article épinglé (is_sticky = true) le plus récent
     * - featured : article à la une (is_featured = true) le plus récent
     *              différent du sticky si possible
     * - latest   : article le plus récent qui n'est ni sticky ni featured
     */
    public function index(): JsonResponse
    {
        // 🧩 Base query : on utilise TES scopes Published + Public
        $baseQuery = Article::query()
            ->published()   // scopePublished du modèle
            ->public()      // scopePublic du modèle
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        // 1) Article épinglé (is_sticky = true) le plus récent
        $sticky = (clone $baseQuery)
            ->sticky()      // scopeSticky : where('is_sticky', true)
            ->first();

        // 2) Article "à la une" (is_featured = true), différent du sticky si possible
        $featuredQuery = (clone $baseQuery)
            ->featured();   // scopeFeatured : where('is_featured', true)

        if ($sticky) {
            $featuredQuery->where('id', '!=', $sticky->id);
        }

        $featured = $featuredQuery->first();

        // 3) Article récent standard :
        //    - ni sticky (is_sticky = false ou NULL)
        //    - ni featured (is_featured = false ou NULL)
        //    - différent du sticky et du featured si ils existent
        $latestQuery = (clone $baseQuery)
            ->where(function ($q) {
                $q->where('is_sticky', false)
                  ->orWhereNull('is_sticky');
            })
            ->where(function ($q) {
                $q->where('is_featured', false)
                  ->orWhereNull('is_featured');
            });

        if ($sticky) {
            $latestQuery->where('id', '!=', $sticky->id);
        }

        if ($featured) {
            $latestQuery->where('id', '!=', $featured->id);
        }

        $latest = $latestQuery->first();

        return response()->json([
            'data' => [
                'sticky'   => $sticky,   // article épinglé
                'featured' => $featured, // article à la une
                'latest'   => $latest,   // article récent ni sticky ni featured
            ],
        ]);
    }
}
