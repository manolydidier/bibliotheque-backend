<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArticleTagController extends Controller
{
    /** Détermine le nom de la colonne d'ordre sur le pivot. */
    protected function pivotOrderColumn(): string
    {
        if (Schema::hasColumn('article_tags', 'position')) return 'position';
        if (Schema::hasColumn('article_tags', 'order')) return 'order';
        return '';
    }

    /** GET /articlestags/{article}/tags : lister les tags de l’article */
    public function index(Article $article)
    {
        $orderCol = $this->pivotOrderColumn();

        $q = $article->tags();
        if ($orderCol) {
            $q->withPivot($orderCol)->orderBy("article_tags.$orderCol");
        }

        $tags = $q->get();

        return response()->json([
            'data' => $tags,
        ]);
    }

    /** POST /articlestags/{article}/tags : attacher 1 tag */
    public function attach(Request $request, Article $article)
    {
        $data = $request->validate([
            'tag_id' => ['required', 'integer', 'exists:tags,id'],
        ]);

        $orderCol = $this->pivotOrderColumn();
        $extra = [];
        if ($orderCol) {
            $max = DB::table('article_tags')
                ->where('article_id', $article->id)
                ->max($orderCol);
            $extra[$orderCol] = is_null($max) ? 0 : ($max + 1);
        }

        $article->tags()->syncWithoutDetaching([
            $data['tag_id'] => $extra
        ]);

        return $this->index($article);
    }

    /** DELETE /articlestags/{article}/tags/{tag} : détacher 1 tag */
    public function detach(Article $article, Tag $tag)
    {
        $article->tags()->detach($tag->id);
        return $this->index($article);
    }

    /** PUT /articlestags/{article}/tags : remplacer tous les tags (sync) */
    public function sync(Request $request, Article $article)
    {
        $payload = $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $ids = array_values(array_unique($payload['tag_ids']));

        $orderCol = $this->pivotOrderColumn();
        $attach = [];

        if ($orderCol) {
            foreach ($ids as $i => $tagId) {
                $attach[$tagId] = [$orderCol => $i];
            }
        } else {
            foreach ($ids as $tagId) {
                $attach[$tagId] = [];
            }
        }

        $article->tags()->sync($attach);

        return $this->index($article);
    }

    /** PATCH /articlestags/{article}/tags/reorder : MAJ de l'ordre */
    public function reorder(Request $request, Article $article)
    {
        $data = $request->validate([
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'exists:tags,id'],
        ]);

        $orderCol = $this->pivotOrderColumn();
        if (!$orderCol) {
            return response()->json([
                'message' => "Aucune colonne d’ordre ('position' ou 'order') sur la table pivot."
            ], 422);
        }

        DB::transaction(function () use ($article, $data, $orderCol) {
            foreach ($data['order'] as $pos => $tagId) {
                DB::table('article_tags')
                    ->where('article_id', $article->id)
                    ->where('tag_id', $tagId)
                    ->update([$orderCol => $pos]);
            }
        });

        return $this->index($article);
    }
}
