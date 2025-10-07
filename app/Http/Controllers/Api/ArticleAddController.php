<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class ArticleAddController extends Controller
{
    /**
     * Règles communes (création).
     */
    private function baseStoreRules(): array
    {
        return [
            'title'   => 'required|string|max:255',
            'slug'    => 'nullable|string|max:255|unique:articles,slug',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',

            'featured_image'     => 'nullable|string|max:255',
            'featured_image_alt' => 'nullable|string|max:255',

            'status'     => ['nullable', Rule::enum(ArticleStatus::class)],
            'visibility' => ['nullable', Rule::enum(ArticleVisibility::class)],
            'password'   => 'nullable|string|max:255|required_if:visibility,' . ArticleVisibility::PASSWORD_PROTECTED->value,

            'published_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'expires_at'   => 'nullable|date',

            'is_featured'    => 'nullable|boolean',
            'is_sticky'      => 'nullable|boolean',
            'allow_comments' => 'nullable|boolean',
            'allow_sharing'  => 'nullable|boolean',
            'allow_rating'   => 'nullable|boolean',

            'author_name'   => 'nullable|string|max:255',
            'author_bio'    => 'nullable|string',
            'author_avatar' => 'nullable|string|max:255',
            'author_id'     => 'nullable|exists:users,id',

            'categories'   => 'nullable|array',
            'categories.*' => 'exists:categories,id',

            'tags'   => 'nullable|array',
            'tags.*' => 'exists:tags,id',

            // meta/seo_data peuvent arriver en array (JSON) ou string JSON
            'meta'     => 'nullable',
            'seo_data' => 'nullable',
        ];
    }

    /**
     * Règles fichiers.
     */
    private function fileRules(): array
    {
        return [
            'featured_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'author_avatar_file'  => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
        ];
    }

    /**
     * Store (JSON simple, sans fichiers).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->baseStoreRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $articleData = $request->only([
                'title','slug','excerpt','content',
                'featured_image','featured_image_alt',
                'status','visibility','password',
                'published_at','scheduled_at','expires_at',
                'is_featured','is_sticky','allow_comments','allow_sharing','allow_rating',
                'author_name','author_bio','author_avatar','author_id',
                'meta','seo_data',
                'tenant_id'
            ]);

            // Normaliser meta/seo_data si envoyés en string JSON
            foreach (['meta','seo_data'] as $jsonish) {
                if (isset($articleData[$jsonish]) && is_string($articleData[$jsonish])) {
                    $decoded = json_decode($articleData[$jsonish], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $articleData[$jsonish] = $decoded;
                    }
                }
            }

            // user / tenant / author par défaut
            $user = Auth::user();
            $articleData['created_by'] = $user->id;
            $articleData['updated_by'] = $user->id;
            $articleData['tenant_id']  = $articleData['tenant_id'] ?? ($user->tenant_id ?? null);
            $articleData['author_id']  = $articleData['author_id'] ?? $user->id;

            $article = Article::create($articleData);

            // Catégories
            if ($request->has('categories')) {
                $categories = $request->input('categories');
                if (is_string($categories)) {
                    $decoded = json_decode($categories, true);
                    $categories = json_last_error() === JSON_ERROR_NONE
                        ? $decoded
                        : array_map('intval', array_filter(explode(',', $categories)));
                }
                if (is_array($categories) && !empty($categories)) {
                    $pivot = [];
                    foreach ($categories as $i => $id) {
                        $pivot[$id] = ['is_primary' => $i === 0, 'sort_order' => $i];
                    }
                    $article->categories()->attach($pivot);
                }
            }

            // Tags
            if ($request->has('tags')) {
                $tags = $request->input('tags');
                if (is_string($tags)) {
                    $decoded = json_decode($tags, true);
                    $tags = json_last_error() === JSON_ERROR_NONE
                        ? $decoded
                        : array_map('intval', array_filter(explode(',', $tags)));
                }
                if (is_array($tags) && !empty($tags)) {
                    $pivot = [];
                    foreach ($tags as $i => $id) {
                        $pivot[$id] = ['sort_order' => $i];
                    }
                    $article->tags()->attach($pivot);
                }
            }

            DB::commit();

            $article->load(['categories', 'tags', 'author', 'createdBy']);

            return response()->json([
                'message' => 'Article créé avec succès',
                'data'    => $article
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la création de l\'article',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store avec upload de fichiers (multipart/form-data).
     * ⚠️ Valide AUSSI les champs de base pour éviter les 500.
     */
    public function storeWithFiles(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            array_merge($this->baseStoreRules(), $this->fileRules())
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $articleData = $request->except(['featured_image_file', 'author_avatar_file', 'categories', 'tags']);

            // Normaliser meta/seo_data si string JSON
            foreach (['meta','seo_data'] as $jsonish) {
                if (isset($articleData[$jsonish]) && is_string($articleData[$jsonish])) {
                    $decoded = json_decode($articleData[$jsonish], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $articleData[$jsonish] = $decoded;
                    }
                }
            }

            // Uploads
            if ($request->hasFile('featured_image_file')) {
                $path = $request->file('featured_image_file')->store('articles/featured', 'public');
                $articleData['featured_image'] = $path;
            }
            if ($request->hasFile('author_avatar_file')) {
                $path = $request->file('author_avatar_file')->store('articles/authors', 'public');
                $articleData['author_avatar'] = $path;
            }

            // user / tenant / author par défaut
            $user = Auth::user();
            $articleData['created_by'] = $user->id;
            $articleData['updated_by'] = $user->id;
            $articleData['tenant_id']  = $articleData['tenant_id'] ?? ($user->tenant_id ?? null);
            $articleData['author_id']  = $articleData['author_id'] ?? $user->id;

            $article = Article::create($articleData);

            // Catégories
            if ($request->has('categories')) {
                $categories = $request->input('categories');
                if (is_string($categories)) {
                    $decoded = json_decode($categories, true);
                    $categories = json_last_error() === JSON_ERROR_NONE
                        ? $decoded
                        : array_map('intval', array_filter(explode(',', $categories)));
                }
                if (is_array($categories) && !empty($categories)) {
                    $pivot = [];
                    foreach ($categories as $i => $id) {
                        $pivot[$id] = ['is_primary' => $i === 0, 'sort_order' => $i];
                    }
                    $article->categories()->attach($pivot);
                }
            }

            // Tags
            if ($request->has('tags')) {
                $tags = $request->input('tags');
                if (is_string($tags)) {
                    $decoded = json_decode($tags, true);
                    $tags = json_last_error() === JSON_ERROR_NONE
                        ? $decoded
                        : array_map('intval', array_filter(explode(',', $tags)));
                }
                if (is_array($tags) && !empty($tags)) {
                    $pivot = [];
                    foreach ($tags as $i => $id) {
                        $pivot[$id] = ['sort_order' => $i];
                    }
                    $article->tags()->attach($pivot);
                }
            }

            DB::commit();

            $article->load(['categories', 'tags', 'author', 'createdBy']);

            return response()->json([
                'message' => 'Article créé avec succès',
                'data'    => $article
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la création de l\'article',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update (JSON simple).
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255|unique:articles,slug,' . $id,
            'excerpt' => 'nullable|string|max:500',
            'content' => 'sometimes|required|string',
            'featured_image' => 'nullable|string|max:255',
            'featured_image_alt' => 'nullable|string|max:255',
            'status' => ['sometimes', Rule::enum(ArticleStatus::class)],
            'visibility' => ['sometimes', Rule::enum(ArticleVisibility::class)],
            'password' => 'nullable|string|max:255|required_if:visibility,' . ArticleVisibility::PASSWORD_PROTECTED->value,
            'published_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'is_featured' => 'nullable|boolean',
            'is_sticky' => 'nullable|boolean',
            'allow_comments' => 'nullable|boolean',
            'allow_sharing' => 'nullable|boolean',
            'allow_rating' => 'nullable|boolean',
            'author_name' => 'nullable|string|max:255',
            'author_bio' => 'nullable|string',
            'author_avatar' => 'nullable|string|max:255',
            'author_id' => 'nullable|exists:users,id',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'meta' => 'nullable|array',
            'seo_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $article = Article::find($id);

            if (!$article) {
                return response()->json(['message' => 'Article non trouvé'], 404);
            }

            $articleData = $request->only([
                'title','slug','excerpt','content',
                'featured_image','featured_image_alt',
                'status','visibility','password',
                'published_at','scheduled_at','expires_at',
                'is_featured','is_sticky','allow_comments','allow_sharing','allow_rating',
                'author_name','author_bio','author_avatar','author_id',
                'meta','seo_data'
            ]);

            $user = Auth::user();
            $articleData['updated_by'] = $user->id;

            $article->update($articleData);

            // Catégories
            if ($request->has('categories')) {
                $categories = $request->input('categories');
                if (is_string($categories)) {
                    $categories = json_decode($categories, true) ?? [];
                }
                if (is_array($categories)) {
                    $pivot = [];
                    foreach ($categories as $i => $idCat) {
                        $pivot[$idCat] = ['is_primary' => $i === 0, 'sort_order' => $i];
                    }
                    $article->categories()->sync($pivot);
                }
            }

            // Tags
            if ($request->has('tags')) {
                $tags = $request->input('tags');
                if (is_string($tags)) {
                    $tags = json_decode($tags, true) ?? [];
                }
                if (is_array($tags)) {
                    $pivot = [];
                    foreach ($tags as $i => $idTag) {
                        $pivot[$idTag] = ['sort_order' => $i];
                    }
                    $article->tags()->sync($pivot);
                }
            }

            DB::commit();

            $article->load(['categories', 'tags', 'author', 'createdBy', 'updatedBy']);

            return response()->json([
                'message' => 'Article mis à jour avec succès',
                'data'    => $article
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'article',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update avec fichiers (multipart/form-data).
     */
    public function updateWithFiles(Request $request, $id)
    {
        $validator = Validator::make($request->all(), $this->fileRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $article = Article::find($id);
            if (!$article) {
                return response()->json(['message' => 'Article non trouvé'], 404);
            }

            $articleData = $request->except(['featured_image_file', 'author_avatar_file', 'categories', 'tags']);

            // Uploads
            if ($request->hasFile('featured_image_file')) {
                if ($article->featured_image) {
                    Storage::disk('public')->delete($article->featured_image);
                }
                $path = $request->file('featured_image_file')->store('articles/featured', 'public');
                $articleData['featured_image'] = $path;
            }

            if ($request->hasFile('author_avatar_file')) {
                if ($article->author_avatar) {
                    Storage::disk('public')->delete($article->author_avatar);
                }
                $path = $request->file('author_avatar_file')->store('articles/authors', 'public');
                $articleData['author_avatar'] = $path;
            }

            $user = Auth::user();
            $articleData['updated_by'] = $user->id;

            $article->update($articleData);

            // Catégories
            if ($request->has('categories')) {
                $categories = $request->input('categories');
                if (is_string($categories)) {
                    $categories = json_decode($categories, true) ?? [];
                }
                if (is_array($categories)) {
                    $pivot = [];
                    foreach ($categories as $i => $idCat) {
                        $pivot[$idCat] = ['is_primary' => $i === 0, 'sort_order' => $i];
                    }
                    $article->categories()->sync($pivot);
                }
            }

            // Tags
            if ($request->has('tags')) {
                $tags = $request->input('tags');
                if (is_string($tags)) {
                    $tags = json_decode($tags, true) ?? [];
                }
                if (is_array($tags)) {
                    $pivot = [];
                    foreach ($tags as $i => $idTag) {
                        $pivot[$idTag] = ['sort_order' => $i];
                    }
                    $article->tags()->sync($pivot);
                }
            }

            DB::commit();

            $article->load(['categories', 'tags', 'author', 'createdBy', 'updatedBy']);

            return response()->json([
                'message' => 'Article mis à jour avec succès',
                'data'    => $article
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'article',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suppression définitive.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $article = Article::find($id);

            if (!$article) {
                return response()->json(['message' => 'Article non trouvé'], 404);
            }

            if ($article->featured_image) {
                Storage::disk('public')->delete($article->featured_image);
            }
            if ($article->author_avatar) {
                Storage::disk('public')->delete($article->author_avatar);
            }

            $article->categories()->detach();
            $article->tags()->detach();

            $article->forceDelete();

            DB::commit();

            return response()->json(['message' => 'Article supprimé définitivement avec succès']);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'article',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete.
     */
    public function softDelete($id)
    {
        try {
            $article = Article::find($id);

            if (!$article) {
                return response()->json(['message' => 'Article non trouvé'], 404);
            }

            $article->delete();

            return response()->json(['message' => 'Article supprimé avec succès (soft delete)']);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'article',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore soft delete.
     */
    public function restore($id)
    {
        try {
            $article = Article::withTrashed()->find($id);

            if (!$article) {
                return response()->json(['message' => 'Article non trouvé'], 404);
            }

            $article->restore();

            return response()->json([
                'message' => 'Article restauré avec succès',
                'data'    => $article
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de la restauration de l\'article',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste corbeille (paginée).
     */
    public function trashed(Request $request)
    {
        try {
            $query = Article::onlyTrashed()
                ->with(['categories', 'tags', 'author'])
                ->orderBy('deleted_at', 'desc');

            $perPage  = $request->get('per_page', 15);
            $articles = $query->paginate($perPage);

            return response()->json([
                'message' => 'Articles supprimés récupérés avec succès',
                'data'    => $articles
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des articles supprimés',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
