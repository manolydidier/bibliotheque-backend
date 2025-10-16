<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArticleMedia;
use App\Enums\MediaType;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArticleMediaController extends Controller
{
    /* =======================
       LISTE GLOBALE (index)
       =======================*/
    public function index(Request $request): JsonResponse
    {
        $query = ArticleMedia::with(['article', 'createdBy', 'updatedBy']);

        // Filtres
        if ($request->filled('type')) {
            if ($type = MediaType::tryFrom($request->type)) {
                $query->byType($type);
            }
        }
        if ($request->filled('article_id')) {
            $query->where('article_id', $request->article_id);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('is_featured')) {
            $query->where('is_featured', filter_var($request->is_featured, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('tenant_id')) {
            $query->byTenant($request->tenant_id);
        }
        if ($q = trim((string)$request->get('q'))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%$q%")
                   ->orWhere('original_filename', 'like', "%$q%");
            });
        }
// … dans index() et byArticle() AVANT $media = … :
if ($request->filled('type')) {
    $raw = $request->type;
    $val = is_string($raw) ? strtolower($raw) : $raw; // ← accepte IMAGE/Video…
    if ($type = \App\Enums\MediaType::tryFrom($val)) {
        $query->byType($type);
    }
}

// Corbeille
if ($request->trashed === 'only') {
    $query->onlyTrashed();
} elseif ($request->trashed === 'with') {
    $query->withTrashed();
}

        // Corbeille
        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // Tri
        $sortField = $request->get('sort_by', 'sort_order');
        $sortDir   = $request->get('sort_dir', 'asc');
        if (in_array($sortField, ['name', 'sort_order', 'created_at', 'size'])) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->ordered();
        }

        // Pagination
        $perPage = (int) $request->get('per_page', 20);
        return response()->json($query->paginate($perPage));
    }

    /* =======================
       CREATE (métadonnées)
       =======================*/
    public function store(Request $request): JsonResponse
    {
        // Ici on crée un média à partir de métadonnées déjà stockées (pas d'upload).
        $validator = Validator::make($request->all(), [
            'article_id'        => 'required|exists:articles,id',
            'name'              => 'required|string|max:255',
            'filename'          => 'required|string|max:255',
            'original_filename' => 'required|string|max:255',
            'path'              => 'required|string',
            'type'              => ['required', Rule::in(array_column(MediaType::cases(), 'value'))],
            'mime_type'         => 'required|string|max:100',
            'size'              => 'nullable|integer',
            'dimensions'        => 'nullable|array',
            'meta'              => 'nullable|array',
            'alt_text'          => 'nullable|array',
            'caption'           => 'nullable|array',
            'sort_order'        => 'nullable|integer',
            'is_featured'       => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Données invalides', 'errors' => $validator->errors()], 422);
        }

        $article = Article::find($request->article_id);

        try {
            $media = new ArticleMedia($request->all());
            $media->tenant_id   = $article->tenant_id;
            $media->url         = Storage::disk('public')->url($request->path);
            if ($request->filled('thumbnail_path')) {
                $media->thumbnail_url = Storage::disk('public')->url($request->thumbnail_path);
            }
            $media->created_by = Auth::id();
            $media->updated_by = Auth::id();
            $media->save();

            return response()->json([
                'message' => 'Média créé avec succès',
                'data' => $media->load(['article', 'createdBy', 'updatedBy'])
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la création du média', 'error' => $e->getMessage()], 500);
        }
    }

    /* =======================
       SHOW
       =======================*/
    public function show(string $id): JsonResponse
    {
        $media = ArticleMedia::with(['article', 'createdBy', 'updatedBy'])->find($id);
        return $media
            ? response()->json($media)
            : response()->json(['message' => 'Média non trouvé'], 404);
    }

    /* =======================
       UPDATE
       =======================*/
    public function update(Request $request, string $id): JsonResponse
    {
        $media = ArticleMedia::find($id);
        if (!$media) return response()->json(['message' => 'Média non trouvé'], 404);

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:255',
            'alt_text'    => 'nullable|array',
            'caption'     => 'nullable|array',
            'sort_order'  => 'nullable|integer',
            'is_featured' => 'nullable|boolean',
            'is_active'   => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Données invalides', 'errors' => $validator->errors()], 422);
        }

        try {
            $media->fill($request->all());
            $media->updated_by = Auth::id();
            $media->save();

            return response()->json([
                'message' => 'Média mis à jour avec succès',
                'data' => $media->load(['article', 'createdBy', 'updatedBy'])
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la mise à jour du média', 'error' => $e->getMessage()], 500);
        }
    }

    /* =======================
       DELETE (soft delete)
       =======================*/
    public function destroy(string $id): JsonResponse
    {
        $media = ArticleMedia::find($id);
        if (!$media) return response()->json(['message' => 'Média non trouvé'], 404);

        try {
            $media->delete();
            return response()->json(['message' => 'Média supprimé avec succès']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la suppression du média', 'error' => $e->getMessage()], 500);
        }
    }

    /* =======================
       RESTORE / FORCE DELETE
       =======================*/
    public function restore(string $id): JsonResponse
    {
        $media = ArticleMedia::onlyTrashed()->find($id);
        if (!$media) return response()->json(['message' => 'Média supprimé non trouvé'], 404);

        try {
            $media->restore();
            return response()->json([
                'message' => 'Média restauré avec succès',
                'data' => $media->load(['article', 'createdBy', 'updatedBy'])
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la restauration du média', 'error' => $e->getMessage()], 500);
        }
    }

    public function forceDelete(string $id): JsonResponse
    {
        $media = ArticleMedia::onlyTrashed()->find($id);
        if (!$media) return response()->json(['message' => 'Média supprimé non trouvé'], 404);

        try {
            $media->deleteFile();
            $media->forceDelete();
            return response()->json(['message' => 'Média définitivement supprimé avec succès']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la suppression définitive du média', 'error' => $e->getMessage()], 500);
        }
    }

    /* =======================
       UPLOAD (fichier)
       =======================*/
    public function upload(Request $request): JsonResponse
    {
        // NB: PHP peut bloquer avant Laravel si la taille dépasse upload_max_filesize/post_max_size
        if (!$request->hasFile('file')) {
            return response()->json([
                'message' => "Aucun fichier reçu. Vérifiez 'upload_max_filesize' (".ini_get('upload_max_filesize').") et 'post_max_size' (".ini_get('post_max_size').")"
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'file'       => 'required|file|max:102400', // 100MB (en Ko)
            'article_id' => 'required|exists:articles,id',
            'name'       => 'required|string|max:255',
            'alt_text'   => 'nullable|array',
            'caption'    => 'nullable|array',
            'is_featured'=> 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Données invalides', 'errors' => $validator->errors()], 422);
        }

        try {
            $file    = $request->file('file');
            $article = Article::findOrFail($request->article_id);

            $mimeType = $file->getMimeType();
            $type     = $this->getMediaTypeFromMime($mimeType);

            $extension   = $file->getClientOriginalExtension();
            $filename    = Str::uuid().'.'.$extension;
            $relativeDir = 'articles/'.$article->id.'/media';
            $path        = $file->storeAs($relativeDir, $filename, 'public');

            $media = new ArticleMedia();
            $media->tenant_id         = $article->tenant_id;
            $media->article_id        = $article->id;
            $media->name              = $request->name;
            $media->filename          = $filename;
            $media->original_filename = $file->getClientOriginalName();
            $media->path              = $path;
            $media->url               = Storage::disk('public')->url($path);
            $media->type              = $type;
            $media->mime_type         = $mimeType;
            $media->size              = $file->getSize();
            $media->alt_text          = $request->input('alt_text', null);
            $media->caption           = $request->input('caption', null);
            $media->is_featured       = (bool) $request->input('is_featured', false);
            $media->is_active         = true;
            $media->created_by        = Auth::id();
            $media->updated_by        = Auth::id();

            if ($type === MediaType::IMAGE)  $this->processImage($media, $file);
            if ($type === MediaType::VIDEO)  $this->processVideo($media, $file);

            $media->save();

            return response()->json([
                'message' => 'Fichier téléchargé avec succès',
                'data'    => $media->load(['article', 'createdBy', 'updatedBy'])
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur lors du téléchargement du fichier', 'error' => $e->getMessage()], 500);
        }
    }

    /* =======================
       PAR ARTICLE
       =======================*/
    public function byArticle(string $articleId, Request $request): JsonResponse
    {
        $article = Article::find($articleId);
        if (!$article) return response()->json(['message' => 'Article non trouvé'], 404);

        $query = ArticleMedia::where('article_id', $articleId)
            ->with(['createdBy', 'updatedBy']);

        // Filtres
        if ($request->filled('type')) {
            if ($type = MediaType::tryFrom($request->type)) $query->byType($type);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('is_featured')) {
            $query->where('is_featured', filter_var($request->is_featured, FILTER_VALIDATE_BOOLEAN));
        }
        if ($q = trim((string)$request->get('q'))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%$q%")
                   ->orWhere('original_filename', 'like', "%$q%");
            });
        }

        // Corbeille
        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // Tri
        $sortField = $request->get('sort_by', 'sort_order');
        $sortDir   = $request->get('sort_dir', 'asc');
        if (in_array($sortField, ['name', 'sort_order', 'created_at', 'size'])) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->ordered();
        }

        // Pagination optionnelle
        if ($request->boolean('paginate') || $request->filled('per_page')) {
            $perPage = (int) $request->get('per_page', 20);
            return response()->json($query->paginate($perPage));
        }

        return response()->json($query->get());
    }

    /* =======================
       TOGGLES unitaires
       =======================*/
    public function toggleActive(string $id): JsonResponse
    {
        $media = ArticleMedia::find($id);
        if (!$media) return response()->json(['message' => 'Média non trouvé'], 404);

        try {
            $media->is_active = !$media->is_active;
            $media->updated_by = Auth::id();
            $media->save();
            return response()->json(['message' => 'Statut actif mis à jour', 'data' => $media]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la mise à jour du statut', 'error' => $e->getMessage()], 500);
        }
    }

    public function toggleFeatured(string $id): JsonResponse
    {
        $media = ArticleMedia::find($id);
        if (!$media) return response()->json(['message' => 'Média non trouvé'], 404);

        try {
            $media->is_featured = !$media->is_featured;
            $media->updated_by = Auth::id();
            $media->save();
            return response()->json(['message' => 'Statut vedette mis à jour', 'data' => $media]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur lors de la mise à jour du statut', 'error' => $e->getMessage()], 500);
        }
    }

    /* =======================
       BULK ACTIONS
       =======================*/
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = Validator::validate($request->all(), [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:article_media,id',
        ]);

        $items = ArticleMedia::whereIn('id', $data['ids'])->get();
        $ok = 0; $errors = [];

        foreach ($items as $m) {
            try { $m->delete(); $ok++; }
            catch (\Throwable $e) { $errors[$m->id] = $e->getMessage(); }
        }

        return response()->json(['deleted' => $ok, 'errors' => $errors]);
    }

    public function bulkToggleActive(Request $request): JsonResponse
    {
        $data = Validator::validate($request->all(), [
            'ids'     => 'required|array|min:1',
            'ids.*'   => 'integer|exists:article_media,id',
            'desired' => 'required|boolean',
        ]);

        $affected = ArticleMedia::whereIn('id', $data['ids'])
            ->update(['is_active' => $data['desired'], 'updated_by' => Auth::id()]);

        return response()->json(['updated' => $affected]);
    }

    public function bulkToggleFeatured(Request $request): JsonResponse
    {
        $data = Validator::validate($request->all(), [
            'ids'     => 'required|array|min:1',
            'ids.*'   => 'integer|exists:article_media,id',
            'desired' => 'required|boolean',
        ]);

        $affected = ArticleMedia::whereIn('id', $data['ids'])
            ->update(['is_featured' => $data['desired'], 'updated_by' => Auth::id()]);

        return response()->json(['updated' => $affected]);
    }

    /* =======================
       Helpers
       =======================*/
    private function getMediaTypeFromMime(string $mimeType): MediaType
    {
        return Str::startsWith($mimeType, 'image/') ? MediaType::IMAGE
            : (Str::startsWith($mimeType, 'video/') ? MediaType::VIDEO
            : (Str::startsWith($mimeType, 'audio/') ? MediaType::AUDIO
            : MediaType::DOCUMENT));
    }

    private function processImage(ArticleMedia $media, $file): void
    {
        try {
            // dimensions
            if (function_exists('getimagesize')) {
                [$w, $h] = getimagesize($file->getPathname());
                $media->dimensions = ['width' => $w, 'height' => $h];
            }

            // miniature (best effort)
            $thumbPath = 'thumbnails/'.pathinfo($media->path, PATHINFO_DIRNAME).'/'.pathinfo($media->path, PATHINFO_FILENAME).'-thumb.jpg';
            $thumbPath = str_replace('articles/', '', $thumbPath); // évite double "articles/articles"
            // Idéal: Intervention Image
            // \Image::make($file->getPathname())->fit(600, 400)->encode('jpg', 80);
            // Storage::disk('public')->put($thumbPath, (string) $img);
            // Ici: on « réserve » l'URL, même si la génération est déléguée ailleurs
            $media->thumbnail_path = $thumbPath;
            $media->thumbnail_url  = Storage::disk('public')->url($thumbPath);
        } catch (\Throwable $e) {
            logger()->warning('processImage error: '.$e->getMessage());
        }
    }

    private function processVideo(ArticleMedia $media, $file): void
    {
        try {
            // Placeholder: idéalement via FFmpeg
            $media->meta = ['duration' => 0, 'bitrate' => 0];

            $thumbPath = 'thumbnails/'.pathinfo($media->path, PATHINFO_FILENAME).'.jpg';
            $media->thumbnail_path = $thumbPath;
            $media->thumbnail_url  = Storage::disk('public')->url($thumbPath);
        } catch (\Throwable $e) {
            logger()->warning('processVideo error: '.$e->getMessage());
        }
    }
}
