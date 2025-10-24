<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleQueryController extends Controller
{
    /**
     * Listing des articles pour le backoffice.
     *
     * Règles:
     * - Par défaut (sans status): uniquement les publiés (status='published') et non soft-deleted.
     * - status=archived|draft|pending|published => filtre direct sur la colonne "status" (toujours non soft-deleted ici).
     * - Les éléments soft-deleted (corbeille) NE SONT PAS renvoyés par cette route.
     * - Toujours renvoyer 'ui_status' (archived si soft-deleted, sinon status).
     *
     * Filtres pris en charge (via query string) :
     *   search, status, visibility, featured, sticky,
     *   date_from, date_to,
     *   rating_min, rating_max,
     *   author_id, author_ids,
     *   category_ids, categories (slug ou name),
     *   tag_ids, tags (slug ou name)
     *
     * Tri: sort_by in [published_at,title,view_count,rating_average,status,created_at,updated_at],
     *      sort_direction asc|desc
     */
    public function index(Request $request)
    {
        $perPage    = (int) $request->get('per_page', 24);
        $page       = (int) $request->get('page', 1);
        $search     = trim((string) $request->get('search', ''));
        $status     = $request->get('status');                  // published|draft|pending|archived (facultatif)
        $visibility = $request->get('visibility');              // public|private|password_protected
        $featured   = (int) $request->get('featured', 0);
        $sticky     = (int) $request->get('sticky', 0);
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');

        $ratingMin  = $request->get('rating_min');
        $ratingMax  = $request->get('rating_max');

        $authorId   = $request->get('author_id');
        $authorIds  = $request->get('author_ids');              // CSV d'ids

        $catIds     = $request->get('category_ids');            // CSV d'ids
        $cats       = $request->get('categories');              // CSV de slug ou de name

        $tagIds     = $request->get('tag_ids');                 // CSV d'ids
        $tags       = $request->get('tags');                    // CSV de slug ou de name

        $sortBy     = $request->get('sort_by', 'published_at');
        $sortDir    = strtolower($request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        // === Query de base: on exclut la corbeille ici (soft-deleted)
        $q = Article::query()
            ->with(['author','categories','tags'])
            ->whereNull('deleted_at');

        // === Recherche plein texte simple
        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('title', 'like', "%{$search}%")
                   ->orWhere('excerpt', 'like', "%{$search}%")
                   ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // === Statut (colonne "status"); 'archived' est un status métier, pas la corbeille
        if ($status) {
            $q->where('status', $status);
        } else {
            // défaut = published
            $q->where('status', 'published');
        }

        // === Visibilité
        if ($visibility) {
            $q->where('visibility', $visibility);
        }

        // === Flags
        if ($featured) $q->where('is_featured', true);
        if ($sticky)   $q->where('is_sticky', true);

        // === Dates (sur published_at)
        if ($dateFrom) $q->whereDate('published_at', '>=', $dateFrom);
        if ($dateTo)   $q->whereDate('published_at', '<=', $dateTo);

        // === Rating
        // rating_average arrive parfois en string (ex: "5.00") => on compare numériquement
        if ($ratingMin !== null && $ratingMin !== '') {
            $q->whereRaw('CAST(rating_average AS DECIMAL(10,2)) >= ?', [floatval($ratingMin)]);
        }
        if ($ratingMax !== null && $ratingMax !== '') {
            $q->whereRaw('CAST(rating_average AS DECIMAL(10,2)) <= ?', [floatval($ratingMax)]);
        }

        // === Auteur
        if (!empty($authorId)) {
            $q->where('author_id', intval($authorId));
        }
        if (!empty($authorIds)) {
            $ids = $this->toIntArray($authorIds);
            if (!empty($ids)) $q->whereIn('author_id', $ids);
        }

        // === Catégories
        if (!empty($catIds)) {
            $ids = $this->toIntArray($catIds);
            if (!empty($ids)) {
                $q->whereHas('categories', function ($qq) use ($ids) {
                    $qq->whereIn('categories.id', $ids);
                });
            }
        }
        if (!empty($cats)) {
            $tokens = $this->toStrArray($cats);
            if (!empty($tokens)) {
                // on matche sur slug OU name
                $q->whereHas('categories', function ($qq) use ($tokens) {
                    $qq->where(function ($w) use ($tokens) {
                        $w->whereIn('categories.slug', $tokens)
                          ->orWhereIn('categories.name', $tokens);
                    });
                });
            }
        }

        // === Tags
        if (!empty($tagIds)) {
            $ids = $this->toIntArray($tagIds);
            if (!empty($ids)) {
                $q->whereHas('tags', function ($qq) use ($ids) {
                    $qq->whereIn('tags.id', $ids);
                });
            }
        }
        if (!empty($tags)) {
            $tokens = $this->toStrArray($tags);
            if (!empty($tokens)) {
                $q->whereHas('tags', function ($qq) use ($tokens) {
                    $qq->where(function ($w) use ($tokens) {
                        $w->whereIn('tags.slug', $tokens)
                          ->orWhereIn('tags.name', $tokens);
                    });
                });
            }
        }

        // === Tri sécurisé
        $allowedSorts = ['published_at','title','view_count','rating_average','status','created_at','updated_at'];
        if (!in_array($sortBy, $allowedSorts, true)) $sortBy = 'published_at';
        $q->orderBy($sortBy, $sortDir);

        // === Pagination
        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        // === Mapping + ui_status
        $items = $paginator->getCollection()->map(function (Article $a) {
            $arr = $a->toArray();
            // Ici on n’envoie jamais de soft-deleted, mais on garde la logique ui_status homogène
            $arr['ui_status'] = $a->deleted_at ? 'archived' : ($a->status ?? 'draft');
            return $arr;
        });

        return response()->json([
            'data' => $items->values(),
            'meta' => [
                'current_page'  => $paginator->currentPage(),
                'last_page'     => $paginator->lastPage(),
                'per_page'      => $paginator->perPage(),
                'total'         => $paginator->total(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
                // Facettes (optionnel): on laisse vide par défaut; à calculer si besoin
                'facets'        => [
                    'categories' => [],
                    'tags'       => [],
                    'authors'    => [],
                ],
            ],
        ]);
    }

    /** @return int[] */
    private function toIntArray($csvOrArray): array
    {
        $arr = is_array($csvOrArray) ? $csvOrArray : explode(',', (string) $csvOrArray);
        $arr = array_map(fn($v) => trim((string)$v), $arr);
        $arr = array_filter($arr, fn($v) => $v !== '');
        $arr = array_map('intval', $arr);
        // retire 0 s’il n’était pas voulu
        return array_values(array_filter($arr, fn($n) => $n > 0));
    }

    /** @return string[] */
    private function toStrArray($csvOrArray): array
    {
        $arr = is_array($csvOrArray) ? $csvOrArray : explode(',', (string) $csvOrArray);
        $arr = array_map(fn($v) => trim((string)$v), $arr);
        $arr = array_filter($arr, fn($v) => $v !== '');
        return array_values($arr);
    }
}
