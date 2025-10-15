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
use Illuminate\Support\Facades\Log;

class ArticleAddController extends Controller
{
    /* =========================================================
       Helpers SEO & payload
    ==========================================================*/

    /**
     * Décoder les champs JSON envoyés en string (JSON) et normaliser seo_data.
     * - Accepte aussi robots_index / robots_follow (à l'intérieur de seo_data) et les range dans robots[index|follow].
     * - Supprime les clés SEO inutiles (keywords, og_*, etc.) pour ne garder que le schéma minimal.
     * - Tronque proprement title/description aux longueurs usuelles.
     */
    private function preparePayload(array $input, ?Article $article = null): array
    {
        // Décodage JSON-ish
        foreach (['meta', 'seo_data'] as $jsonish) {
            if (array_key_exists($jsonish, $input) && is_string($input[$jsonish])) {
                $decoded = json_decode($input[$jsonish], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $input[$jsonish] = $decoded;
                }
            }
        }

        // Normalisation SEO minimale
        if (isset($input['seo_data']) && is_array($input['seo_data'])) {
            $input['seo_data'] = $this->cleanSeoData($input['seo_data'], $input, $article);
        }

        return $input;
    }

    /**
     * Nettoie/normalise l'objet seo_data:
     *  - meta_title: string <=60 (fallback: title)
     *  - meta_description: string <=160 (fallback: excerpt)
     *  - canonical_url: URL valide ou null
     *  - robots: { index: bool, follow: bool } (fallback true/true)
     *  - supprime toute autre clé (keywords, og_*, twitter:*, etc.)
     */
    private function cleanSeoData(array $seo, array $rawInput = [], ?Article $article = null): array
    {
        $titleFallback = $rawInput['title'] ?? ($article?->title ?? '');
        $excerptFallback = $rawInput['excerpt'] ?? ($article?->excerpt ?? '');

        // Récup index/follow sous différentes formes
        $robotsArr = isset($seo['robots']) && is_array($seo['robots']) ? $seo['robots'] : [];
        $robotsIndex  = $robotsArr['index']  ?? ($seo['robots_index']  ?? true);
        $robotsFollow = $robotsArr['follow'] ?? ($seo['robots_follow'] ?? true);

        // Canonical: valider l'URL (sinon null)
        $canonical = $seo['canonical_url'] ?? null;
        $canonical = (is_string($canonical) && filter_var($canonical, FILTER_VALIDATE_URL)) ? $canonical : null;

        $metaTitle = trim((string) ($seo['meta_title'] ?? $titleFallback));
        $metaDesc  = trim((string) ($seo['meta_description'] ?? $excerptFallback));

        // Tronquages doux (pas de "…", on garde propre)
        $metaTitle = Str::limit($metaTitle, 60, '');
        $metaDesc  = Str::limit($metaDesc, 160, '');

        // Construire l'objet final minimal
        $clean = [
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
            'canonical_url'    => $canonical,
            'robots'           => [
                'index'  => (bool) $robotsIndex,
                'follow' => (bool) $robotsFollow,
            ],
        ];

        return $clean;
    }

    /**
     * Convertit une liste d'IDs en array<int> depuis array|string JSON ou "1,2,3".
     */
    private function parseIdsList($val): array
    {
        if (is_array($val)) return array_values(array_filter(array_map('intval', $val)));
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map('intval', $decoded)));
            }
            return array_values(array_filter(array_map('intval', array_map('trim', explode(',', $val)))));
        }
        return [];
    }

    /* =========================================================
       Règles de validation
    ==========================================================*/

    /**
     * Règles communes (création).
     * NB: on valide aussi les champs imbriqués de seo_data.*
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

            // meta/seo_data en array (si string JSON, on le convertit AVANT validation)
            'meta'     => 'nullable|array',
            'seo_data' => 'nullable|array',
            // Règles SEO minimales
            'seo_data.meta_title'       => 'nullable|string|max:255',
            'seo_data.meta_description' => 'nullable|string|max:1000',
            'seo_data.canonical_url'    => 'nullable|url|max:255',
            'seo_data.robots'           => 'nullable|array',
            'seo_data.robots.index'     => 'nullable|boolean',
            'seo_data.robots.follow'    => 'nullable|boolean',
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

    /* =========================================================
       Store (JSON)
    ==========================================================*/
    public function store(Request $request)
    {
        // Préparer/normaliser le payload (décodage JSON + SEO clean)
        $data = $this->preparePayload($request->all());

        $validator = Validator::make($data, $this->baseStoreRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $articleData = array_intersect_key($data, array_flip([
                'title','slug','excerpt','content',
                'featured_image','featured_image_alt',
                'status','visibility','password',
                'published_at','scheduled_at','expires_at',
                'is_featured','is_sticky','allow_comments','allow_sharing','allow_rating',
                'author_name','author_bio','author_avatar','author_id',
                'meta','seo_data',
                'tenant_id'
            ]));

            // user / tenant / author par défaut
            $user = Auth::user();
            $articleData['created_by'] = $user->id;
            $articleData['updated_by'] = $user->id;
            $articleData['tenant_id']  = $articleData['tenant_id'] ?? ($user->tenant_id ?? null);
            $articleData['author_id']  = $articleData['author_id'] ?? $user->id;

            $article = Article::create($articleData);

            // Catégories
            if (array_key_exists('categories', $data)) {
                $categories = $this->parseIdsList($data['categories']);
                if (!empty($categories)) {
                    $pivot = [];
                    foreach ($categories as $i => $cid) {
                        $pivot[$cid] = ['is_primary' => $i === 0, 'sort_order' => $i];
                    }
                    $article->categories()->attach($pivot);
                }
            }

            // Tags
            if (array_key_exists('tags', $data)) {
                $tags = $this->parseIdsList($data['tags']);
                if (!empty($tags)) {
                    $pivot = [];
                    foreach ($tags as $i => $tid) {
                        $pivot[$tid] = ['sort_order' => $i];
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

    /* =========================================================
       Store (multipart + fichiers)
    ==========================================================*/
    public function storeWithFiles(Request $request)
    {
        // On prépare le payload AVANT validation pour permettre les règles imbriquées
        $data = $this->preparePayload($request->all());

        $validator = Validator::make(
            $data,
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

            $articleData = $data;
            unset($articleData['featured_image_file'], $articleData['author_avatar_file'], $articleData['categories'], $articleData['tags']);

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
            if (array_key_exists('categories', $data)) {
                $categories = $this->parseIdsList($data['categories']);
                if (!empty($categories)) {
                    $pivot = [];
                    foreach ($categories as $i => $cid) {
                        $pivot[$cid] = ['is_primary' => $i === 0, 'sort_order' => $i];
                    }
                    $article->categories()->attach($pivot);
                }
            }

            // Tags
            if (array_key_exists('tags', $data)) {
                $tags = $this->parseIdsList($data['tags']);
                if (!empty($tags)) {
                    $pivot = [];
                    foreach ($tags as $i => $tid) {
                        $pivot[$tid] = ['sort_order' => $i];
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

    /* =========================================================
       Update (JSON)
    ==========================================================*/
    public function update(Request $request, $id)
    {
        // Normaliser le payload avant validation
        $data = $this->preparePayload($request->all());

        $validator = Validator::make($data, [
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

            'meta'     => 'nullable|array',
            'seo_data' => 'nullable|array',
            // SEO imbriqué
            'seo_data.meta_title'       => 'nullable|string|max:255',
            'seo_data.meta_description' => 'nullable|string|max:1000',
            'seo_data.canonical_url'    => 'nullable|url|max:255',
            'seo_data.robots'           => 'nullable|array',
            'seo_data.robots.index'     => 'nullable|boolean',
            'seo_data.robots.follow'    => 'nullable|boolean',
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

            $articleData = array_intersect_key($data, array_flip([
                'title','slug','excerpt','content',
                'featured_image','featured_image_alt',
                'status','visibility','password',
                'published_at','scheduled_at','expires_at',
                'is_featured','is_sticky','allow_comments','allow_sharing','allow_rating',
                'author_name','author_bio','author_avatar','author_id',
                'meta','seo_data'
            ]));

            $user = Auth::user();
            $articleData['updated_by'] = $user->id;

            $article->update($articleData);

            // Catégories
            if (array_key_exists('categories', $data)) {
                $categories = $this->parseIdsList($data['categories']);
                $pivot = [];
                foreach ($categories as $i => $cid) {
                    $pivot[$cid] = ['is_primary' => $i === 0, 'sort_order' => $i];
                }
                $article->categories()->sync($pivot);
            }

            // Tags
            if (array_key_exists('tags', $data)) {
                $tags = $this->parseIdsList($data['tags']);
                $pivot = [];
                foreach ($tags as $i => $tid) {
                    $pivot[$tid] = ['sort_order' => $i];
                }
                $article->tags()->sync($pivot);
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

    /* =========================================================
       Update (multipart + fichiers)
    ==========================================================*/
    public function updateWithFiles(Request $request, $id)
    {
        // On prépare aussi le payload (seo_data/meta) pour pouvoir accepter multipart string JSON
        $data = $this->preparePayload($request->all());

        // Valider fichiers (+ optionnellement seo_data propre si présent)
        $validator = Validator::make($data, array_merge($this->fileRules(), [
            'meta'     => 'nullable|array',
            'seo_data' => 'nullable|array',
            'seo_data.meta_title'       => 'nullable|string|max:255',
            'seo_data.meta_description' => 'nullable|string|max:1000',
            'seo_data.canonical_url'    => 'nullable|url|max:255',
            'seo_data.robots'           => 'nullable|array',
            'seo_data.robots.index'     => 'nullable|boolean',
            'seo_data.robots.follow'    => 'nullable|boolean',
        ]));

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

            $articleData = $data;
            unset($articleData['featured_image_file'], $articleData['author_avatar_file'], $articleData['categories'], $articleData['tags']);

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
            if (array_key_exists('categories', $data)) {
                $categories = $this->parseIdsList($data['categories']);
                $pivot = [];
                foreach ($categories as $i => $cid) {
                    $pivot[$cid] = ['is_primary' => $i === 0, 'sort_order' => $i];
                }
                $article->categories()->sync($pivot);
            }

            // Tags
            if (array_key_exists('tags', $data)) {
                $tags = $this->parseIdsList($data['tags']);
                $pivot = [];
                foreach ($tags as $i => $tid) {
                    $pivot[$tid] = ['sort_order' => $i];
                }
                $article->tags()->sync($pivot);
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

    /* =========================================================
       Destroy (hard delete)
    ==========================================================*/
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

    /* =========================================================
       Soft delete / Restore / Corbeille
    ==========================================================*/
    public function softDelete($id)
    {
        try {
            $affected = Article::whereKey($id)->update([
                'status'     => 'draft',
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

            if (!$affected) {
                return response()->json(['message' => 'Article non trouvé'], 404);
            }

            return response()->json([
                'message' => 'Article passé en draft et marqué comme supprimé (deleted_at)',
                'id'      => $id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l’article',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function restore($id)
    {
        try {
            $article = Article::withTrashed()->find($id);

            if (!$article) {
                return response()->json(['message' => 'Article non trouvé'], 404);
            }

            $article->restore();
            $article->status = 'published';
            $article->save();

            $article->load(['categories', 'tags', 'author']);

            return response()->json([
                'message' => 'Article restauré avec succès',
                'data'    => $article,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de la restauration de l\'article',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function corbeille(Request $request)
    {
        $t0 = microtime(true);

        try {
            $perPage = (int) $request->get('per_page', 15);

            $query = Article::onlyTrashed()
                ->with(['categories', 'tags', 'author'])
                ->orderByDesc('deleted_at');

            if ($request->filled('search')) {
                $s = $request->query('search');
                $query->where(function ($q) use ($s) {
                    $q->where('title', 'like', "%{$s}%")
                      ->orWhere('excerpt', 'like', "%{$s}%")
                      ->orWhere('content', 'like', "%{$s}%");
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            $paginator = $query->paginate($perPage);

            return response()->json([
                'message' => 'Corbeille récupérée avec succès',
                'data'    => $paginator,
            ]);
        } catch (\Throwable $e) {
            Log::error('Erreur corbeille', [
                'user_id' => optional($request->user())->id,
                'ip'      => $request->ip(),
                'query'   => $request->only(['page','per_page','search','status']),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de la récupération de la corbeille',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
