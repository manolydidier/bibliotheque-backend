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
                'title', 'slug', 'excerpt', 'content', 'featured_image', 
                'featured_image_alt', 'status', 'visibility', 'password',
                'published_at', 'scheduled_at', 'expires_at', 'is_featured',
                'is_sticky', 'allow_comments', 'allow_sharing', 'allow_rating',
                'author_name', 'author_bio', 'author_avatar', 'author_id',
                'meta', 'seo_data'
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

            // Attachement des catégories et tags
            if ($request->has('categories')) {
                $categoriesData = [];
                foreach ($request->categories as $index => $categoryId) {
                    $categoriesData[$categoryId] = ['is_primary' => $index === 0, 'sort_order' => $index];
                }
                $article->categories()->attach($categoriesData);
            }

            if ($request->has('tags')) {
                $tagsData = [];
                foreach ($request->tags as $index => $tagId) {
                    $tagsData[$tagId] = ['sort_order' => $index];
                }
                $article->tags()->attach($tagsData);
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
}