<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Article;
use App\Http\Controllers\Controller;

class SearchSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $limit = (int) $request->get('limit', 8);

        if ($limit <= 0) {
            $limit = 8;
        } elseif ($limit > 20) {
            $limit = 20;
        }

        if ($q === '') {
          return response()->json(['suggestions' => []]);
        }

        $articleTitleSuggestions = Article::query()
            ->select('title')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', $q.'%')
                        ->orWhere('title', 'like', '% '.$q.'%');
                });
            })
            ->orderBy('title')
            ->limit($limit * 3)
            ->pluck('title')
            ->filter()
            ->values();

        $suggestions = $articleTitleSuggestions
            ->unique(function ($v) { return mb_strtolower($v); })
            ->take($limit)
            ->map(fn($term) => [
                'label' => $term,
                'query' => $term,
            ])
            ->values();

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }
}
