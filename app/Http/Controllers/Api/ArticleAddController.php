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
     * Store a newly created article in storage.
     */
    public function store(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:articles,slug',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featured_image' => 'nullable|string|max:255',
            'featured_image_alt' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::enum(ArticleStatus::class)],
            'visibility' => ['nullable', Rule::enum(ArticleVisibility::class)],
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
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Préparation des données
            $articleData = $request->only([
                'title',
                'slug',
                'excerpt',
                'content',
                'featured_image',
                'featured_image_alt',
                'status',
                'visibility',
                'password',
                'published_at',
                'scheduled_at',
                'expires_at',
                'is_featured',
                'is_sticky',
                'allow_comments',
                'allow_sharing',
                'allow_rating',
                'author_name',
                'author_bio',
                'author_avatar',
                'author_id',
                'meta',
                'seo_data'
            ]);

            // Ajout des données utilisateur
            $user = Auth::user();
            $articleData['created_by'] = $user->id;
            $articleData['updated_by'] = $user->id;

            // Gestion du tenant_id (à adapter selon votre logique d'authentification)
            if (empty($articleData['tenant_id'])) {
                $articleData['tenant_id'] = $user->tenant_id ?? null;
            }

            // Gestion de l'auteur par défaut
            if (empty($articleData['author_id'])) {
                $articleData['author_id'] = $user->id;
            }

            // Création de l'article
            $article = Article::create($articleData);

            // Attachement des catégories
            if ($request->has('categories')) {
                $categoriesData = [];
                foreach ($request->categories as $index => $categoryId) {
                    $categoriesData[$categoryId] = [
                        'is_primary' => $index === 0, // Première catégorie = primaire
                        'sort_order' => $index
                    ];
                }
                $article->categories()->attach($categoriesData);
            }

            // Attachement des tags
            if ($request->has('tags')) {
                $tagsData = [];
                foreach ($request->tags as $index => $tagId) {
                    $tagsData[$tagId] = ['sort_order' => $index];
                }
                $article->tags()->attach($tagsData);
            }

            DB::commit();

            // Charger les relations pour la réponse
            $article->load(['categories', 'tags', 'author', 'createdBy']);

            return response()->json([
                'message' => 'Article créé avec succès',
                'data' => $article
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la création de l\'article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store with file upload handling (version alternative)
     */
    public function storeWithFiles(Request $request)
    {
        // Validation supplémentaire pour les fichiers
        $validator = Validator::make($request->all(), [
            'featured_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'author_avatar_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $articleData = $request->except(['featured_image_file', 'author_avatar_file', 'categories', 'tags']);

            // Gestion de l'upload de l'image featured
            if ($request->hasFile('featured_image_file')) {
                $path = $request->file('featured_image_file')->store('articles/featured', 'public');
                $articleData['featured_image'] = $path;
            }

            // Gestion de l'upload de l'avatar auteur
            if ($request->hasFile('author_avatar_file')) {
                $path = $request->file('author_avatar_file')->store('articles/authors', 'public');
                $articleData['author_avatar'] = $path;
            }

            // Suite identique à la méthode store...
            $user = Auth::user();
            $articleData['created_by'] = $user->id;
            $articleData['updated_by'] = $user->id;

            if (empty($articleData['tenant_id'])) {
                $articleData['tenant_id'] = $user->tenant_id ?? null;
            }

            if (empty($articleData['author_id'])) {
                $articleData['author_id'] = $user->id;
            }

            $article = Article::create($articleData);

            // CORRECTION : Attachement des catégories avec vérification du type
            if ($request->has('categories')) {
                $categories = $request->categories;

                // Si categories est une string, convertir en array
                if (is_string($categories)) {
                    // Essayez de décoder JSON d'abord
                    $decoded = json_decode($categories, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $categories = $decoded;
                    } else {
                        // Sinon, split par des virgules
                        $categories = array_map('intval', array_filter(explode(',', $categories)));
                    }
                }

                // S'assurer que c'est un array avant de faire le foreach
                if (is_array($categories) && !empty($categories)) {
                    $categoriesData = [];
                    foreach ($categories as $index => $categoryId) {
                        $categoriesData[$categoryId] = [
                            'is_primary' => $index === 0,
                            'sort_order' => $index
                        ];
                    }
                    $article->categories()->attach($categoriesData);
                }
            }

            // CORRECTION : Attachement des tags avec vérification du type
            if ($request->has('tags')) {
                $tags = $request->tags;

                // Si tags est une string, convertir en array
                if (is_string($tags)) {
                    // Essayez de décoder JSON d'abord
                    $decoded = json_decode($tags, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $tags = $decoded;
                    } else {
                        // Sinon, split par des virgules
                        $tags = array_map('intval', array_filter(explode(',', $tags)));
                    }
                }

                // S'assurer que c'est un array avant de faire le foreach
                if (is_array($tags) && !empty($tags)) {
                    $tagsData = [];
                    foreach ($tags as $index => $tagId) {
                        $tagsData[$tagId] = ['sort_order' => $index];
                    }
                    $article->tags()->attach($tagsData);
                }
            }

            DB::commit();

            $article->load(['categories', 'tags', 'author', 'createdBy']);

            return response()->json([
                'message' => 'Article créé avec succès',
                'data' => $article
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de la création de l\'article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

      /**
     * Update the specified article.
     */
    public function update(Request $request, $id)
    {
        // Validation des données
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
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $article = Article::find($id);

            if (!$article) {
                return response()->json([
                    'message' => 'Article non trouvé'
                ], 404);
            }

            // Préparation des données
            $articleData = $request->only([
                'title', 'slug', 'excerpt', 'content', 'featured_image', 
                'featured_image_alt', 'status', 'visibility', 'password',
                'published_at', 'scheduled_at', 'expires_at', 'is_featured',
                'is_sticky', 'allow_comments', 'allow_sharing', 'allow_rating',
                'author_name', 'author_bio', 'author_avatar', 'author_id',
                'meta', 'seo_data'
            ]);

            // Mise à jour de l'utilisateur qui modifie
            $user = Auth::user();
            $articleData['updated_by'] = $user->id;

            // Mise à jour de l'article
            $article->update($articleData);

            // Mise à jour des catégories
            if ($request->has('categories')) {
                $categories = $request->categories;
                
                if (is_string($categories)) {
                    $categories = json_decode($categories, true) ?? [];
                }
                
                if (is_array($categories) && !empty($categories)) {
                    $categoriesData = [];
                    foreach ($categories as $index => $categoryId) {
                        $categoriesData[$categoryId] = [
                            'is_primary' => $index === 0,
                            'sort_order' => $index
                        ];
                    }
                    $article->categories()->sync($categoriesData);
                }
            }

            // Mise à jour des tags
            if ($request->has('tags')) {
                $tags = $request->tags;
                
                if (is_string($tags)) {
                    $tags = json_decode($tags, true) ?? [];
                }
                
                if (is_array($tags) && !empty($tags)) {
                    $tagsData = [];
                    foreach ($tags as $index => $tagId) {
                        $tagsData[$tagId] = ['sort_order' => $index];
                    }
                    $article->tags()->sync($tagsData);
                }
            }

            DB::commit();

            // Recharger les relations
            $article->load(['categories', 'tags', 'author', 'createdBy', 'updatedBy']);

            return response()->json([
                'message' => 'Article mis à jour avec succès',
                'data' => $article
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update article with files.
     */
    public function updateWithFiles(Request $request, $id)
    {
        // Validation supplémentaire pour les fichiers
        $validator = Validator::make($request->all(), [
            'featured_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'author_avatar_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $article = Article::find($id);

            if (!$article) {
                return response()->json([
                    'message' => 'Article non trouvé'
                ], 404);
            }

            $articleData = $request->except(['featured_image_file', 'author_avatar_file', 'categories', 'tags']);

            // Gestion de l'upload de l'image featured
            if ($request->hasFile('featured_image_file')) {
                // Supprimer l'ancienne image si elle existe
                if ($article->featured_image) {
                    Storage::disk('public')->delete($article->featured_image);
                }
                
                $path = $request->file('featured_image_file')->store('articles/featured', 'public');
                $articleData['featured_image'] = $path;
            }

            // Gestion de l'upload de l'avatar auteur
            if ($request->hasFile('author_avatar_file')) {
                // Supprimer l'ancien avatar si il existe
                if ($article->author_avatar) {
                    Storage::disk('public')->delete($article->author_avatar);
                }
                
                $path = $request->file('author_avatar_file')->store('articles/authors', 'public');
                $articleData['author_avatar'] = $path;
            }

            // Mise à jour de l'utilisateur qui modifie
            $user = Auth::user();
            $articleData['updated_by'] = $user->id;

            // Mise à jour de l'article
            $article->update($articleData);

            // Mise à jour des catégories et tags (même logique que dans update)
            if ($request->has('categories')) {
                $categories = $request->categories;
                
                if (is_string($categories)) {
                    $categories = json_decode($categories, true) ?? [];
                }
                
                if (is_array($categories) && !empty($categories)) {
                    $categoriesData = [];
                    foreach ($categories as $index => $categoryId) {
                        $categoriesData[$categoryId] = [
                            'is_primary' => $index === 0,
                            'sort_order' => $index
                        ];
                    }
                    $article->categories()->sync($categoriesData);
                }
            }

            if ($request->has('tags')) {
                $tags = $request->tags;
                
                if (is_string($tags)) {
                    $tags = json_decode($tags, true) ?? [];
                }
                
                if (is_array($tags) && !empty($tags)) {
                    $tagsData = [];
                    foreach ($tags as $index => $tagId) {
                        $tagsData[$tagId] = ['sort_order' => $index];
                    }
                    $article->tags()->sync($tagsData);
                }
            }

            DB::commit();

            $article->load(['categories', 'tags', 'author', 'createdBy', 'updatedBy']);

            return response()->json([
                'message' => 'Article mis à jour avec succès',
                'data' => $article
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified article permanently.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $article = Article::find($id);

            if (!$article) {
                return response()->json([
                    'message' => 'Article non trouvé'
                ], 404);
            }

            // Supprimer les fichiers associés
            if ($article->featured_image) {
                Storage::disk('public')->delete($article->featured_image);
            }
            if ($article->author_avatar) {
                Storage::disk('public')->delete($article->author_avatar);
            }

            // Supprimer les relations
            $article->categories()->detach();
            $article->tags()->detach();

            // Supprimer l'article définitivement
            $article->forceDelete();

            DB::commit();

            return response()->json([
                'message' => 'Article supprimé définitivement avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete the specified article.
     */
    public function softDelete($id)
    {
        try {
            $article = Article::find($id);

            if (!$article) {
                return response()->json([
                    'message' => 'Article non trouvé'
                ], 404);
            }

            $article->delete();

            return response()->json([
                'message' => 'Article supprimé avec succès (soft delete)'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft deleted article.
     */
    public function restore($id)
    {
        try {
            $article = Article::withTrashed()->find($id);

            if (!$article) {
                return response()->json([
                    'message' => 'Article non trouvé'
                ], 404);
            }

            $article->restore();

            return response()->json([
                'message' => 'Article restauré avec succès',
                'data' => $article
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la restauration de l\'article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trashed articles.
     */
    public function trashed(Request $request)
    {
        try {
            $query = Article::onlyTrashed()
                ->with(['categories', 'tags', 'author'])
                ->orderBy('deleted_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $articles = $query->paginate($perPage);

            return response()->json([
                'message' => 'Articles supprimés récupérés avec succès',
                'data' => $articles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des articles supprimés',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
