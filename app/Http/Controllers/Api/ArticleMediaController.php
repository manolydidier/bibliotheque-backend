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
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Autorisation
        // if (!Gate::allows('viewAny', ArticleMedia::class)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        $query = ArticleMedia::with(['article', 'createdBy', 'updatedBy']);

        // Filtrage par type
        if ($request->has('type')) {
            $type = MediaType::tryFrom($request->type);
            if ($type) {
                $query->byType($type);
            }
        }

        // Filtrage par article
        if ($request->has('article_id')) {
            $query->where('article_id', $request->article_id);
        }

        // Filtrage par statut actif
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Filtrage par statut vedette
        if ($request->has('is_featured')) {
            $query->where('is_featured', filter_var($request->is_featured, FILTER_VALIDATE_BOOLEAN));
        }

        // Filtrage par tenant
        if ($request->has('tenant_id')) {
            $query->byTenant($request->tenant_id);
        }

        // Tri
        $sortField = $request->get('sort_by', 'sort_order');
        $sortDirection = $request->get('sort_dir', 'asc');
        
        if (in_array($sortField, ['name', 'sort_order', 'created_at', 'size'])) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->ordered();
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $media = $query->paginate($perPage);

        return response()->json($media);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // Autorisation
        // if (!Gate::allows('create', ArticleMedia::class)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:51200',  // ← Cette validation échoue
            'article_id' => 'required|exists:articles,id',
            'name' => 'required|string|max:255',
            'filename' => 'required|string|max:255',
            'original_filename' => 'required|string|max:255',
            'path' => 'required|string',
            'type' => ['required', Rule::in(array_column(MediaType::cases(), 'value'))],
            'mime_type' => 'required|string|max:100',
            'size' => 'nullable|integer',
            'dimensions' => 'nullable|array',
            'meta' => 'nullable|array',
            'alt_text' => 'nullable|array',
            'caption' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que l'article appartient au tenant
        $article = Article::find($request->article_id);
        if ($request->tenant_id && $article->tenant_id != $request->tenant_id) {
            return response()->json([
                'message' => "L'article n'appartient pas à ce tenant"
            ], 422);
        }

        try {
            $media = new ArticleMedia($request->all());
            
            // Définir les URLs
            $media->url = Storage::disk('public')->url($request->path);
            
            if ($request->has('thumbnail_path')) {
                $media->thumbnail_url = Storage::disk('public')->url($request->thumbnail_path);
            }
            
            // Définir l'utilisateur connecté comme créateur
            $user = Auth::user();
            $media->created_by = $user->id;
            $media->updated_by = $user->id;
            
            $media->save();

            return response()->json([
                'message' => 'Média créé avec succès',
                'data' => $media->load(['article', 'createdBy', 'updatedBy'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création du média',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $media = ArticleMedia::with(['article', 'createdBy', 'updatedBy'])->find($id);

        if (!$media) {
            return response()->json(['message' => 'Média non trouvé'], 404);
        }

        // Autorisation
        // if (!Gate::allows('view', $media)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        return response()->json($media);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $media = ArticleMedia::find($id);

        if (!$media) {
            return response()->json(['message' => 'Média non trouvé'], 404);
        }

        // Autorisation
        // if (!Gate::allows('update', $media)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'alt_text' => 'nullable|array',
            'caption' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $media->fill($request->all());
            $media->updated_by = $user->id;
            $media->save();

            return response()->json([
                'message' => 'Média mis à jour avec succès',
                'data' => $media->load(['article', 'createdBy', 'updatedBy'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du média',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $media = ArticleMedia::find($id);

        if (!$media) {
            return response()->json(['message' => 'Média non trouvé'], 404);
        }

        // Autorisation
        // if (!Gate::allows('delete', $media)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        try {
            $media->delete();

            return response()->json([
                'message' => 'Média supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression du média',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore the specified soft deleted resource.
     */
    public function restore(string $id): JsonResponse
    {
        $media = ArticleMedia::onlyTrashed()->find($id);

        if (!$media) {
            return response()->json(['message' => 'Média supprimé non trouvé'], 404);
        }

        // Autorisation
        // if (!Gate::allows('restore', $media)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        try {
            $media->restore();

            return response()->json([
                'message' => 'Média restauré avec succès',
                'data' => $media->load(['article', 'createdBy', 'updatedBy'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la restauration du média',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force delete the specified resource.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $media = ArticleMedia::onlyTrashed()->find($id);

        if (!$media) {
            return response()->json(['message' => 'Média supprimé non trouvé'], 404);
        }

        // Autorisation
        // if (!Gate::allows('forceDelete', $media)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        try {
            // Supprimer physiquement le fichier
            $media->deleteFile();
            
            // Supprimer définitivement l'enregistrement
            $media->forceDelete();

            return response()->json([
                'message' => 'Média définitivement supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression définitive du média',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a media file.
     */
    public function upload(Request $request): JsonResponse
    {
        // Autorisation
        // if (!Gate::allows('create', ArticleMedia::class)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        $validator = Validator::make($request->all(), [
             'file' => 'required|file|max:51200', // 50MB max
            'article_id' => 'required|exists:articles,id',
            'name' => 'required|string|max:255',
            'alt_text' => 'nullable|array',
            'caption' => 'nullable|array',
            'is_featured' => 'nullable|boolean',
        ]);
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            logger()->info('File info:', [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'error' => $file->getError()
            ]);
}
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $article = Article::find($request->article_id);
            
            // Déterminer le type de média
            $mimeType = $file->getMimeType();
            $type = $this->getMediaTypeFromMime($mimeType);
            
            // Générer un nom de fichier unique
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            
            // Déterminer le chemin de stockage
            $path = 'articles/' . $article->id . '/media';
            $storagePath = $file->storeAs($path, $filename, 'public');
            
            // Créer l'enregistrement de média
            $media = new ArticleMedia();
            $media->tenant_id = $article->tenant_id;
            $media->article_id = $article->id;
            $media->name = $request->name;
            $media->filename = $filename;
            $media->original_filename = $file->getClientOriginalName();
            $media->path = $storagePath;
            $media->url = Storage::disk('public')->url($storagePath);
            $media->type = $type;
            $media->mime_type = $mimeType;
            $media->size = $file->getSize();
            $media->alt_text = $request->alt_text;
            $media->caption = $request->caption;
            $media->is_featured = $request->is_featured ?? false;
            $media->created_by = Auth::id();
            $media->updated_by = Auth::id();
            
            // Traitement spécifique selon le type
            if ($type === MediaType::IMAGE) {
                $this->processImage($media, $file);
            } elseif ($type === MediaType::VIDEO) {
                $this->processVideo($media, $file);
            }
            
            $media->save();

            return response()->json([
                'message' => 'Fichier téléchargé avec succès',
                'data' => $media->load(['article', 'createdBy', 'updatedBy'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du téléchargement du fichier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get media by article.
     */
    public function byArticle(string $articleId, Request $request): JsonResponse
    {
        $article = Article::find($articleId);
        
        if (!$article) {
            return response()->json(['message' => 'Article non trouvé'], 404);
        }

        // Autorisation
        // if (!Gate::allows('view', $article)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        $query = ArticleMedia::where('article_id', $articleId)
            ->with(['createdBy', 'updatedBy'])
            ->ordered();

        // Filtrer par type si spécifié
        if ($request->has('type')) {
            $type = MediaType::tryFrom($request->type);
            if ($type) {
                $query->byType($type);
            }
        }

        $media = $query->get();

        return response()->json($media);
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(string $id): JsonResponse
    {
        $media = ArticleMedia::find($id);

        if (!$media) {
            return response()->json(['message' => 'Média non trouvé'], 404);
        }

        // Autorisation
        // if (!Gate::allows('update', $media)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        try {
            $media->is_active = !$media->is_active;
            $media->updated_by = Auth::id();
            $media->save();

            return response()->json([
                'message' => 'Statut actif mis à jour avec succès',
                'data' => $media
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(string $id): JsonResponse
    {
        $media = ArticleMedia::find($id);

        if (!$media) {
            return response()->json(['message' => 'Média non trouvé'], 404);
        }

        // Autorisation
        // if (!Gate::allows('update', $media)) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        try {
            $media->is_featured = !$media->is_featured;
            $media->updated_by = Auth::id();
            $media->save();

            return response()->json([
                'message' => 'Statut vedette mis à jour avec succès',
                'data' => $media
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine media type from MIME type.
     */
    private function getMediaTypeFromMime(string $mimeType): MediaType
    {
        if (Str::startsWith($mimeType, 'image/')) {
            return MediaType::IMAGE;
        } elseif (Str::startsWith($mimeType, 'video/')) {
            return MediaType::VIDEO;
        } elseif (Str::startsWith($mimeType, 'audio/')) {
            return MediaType::AUDIO;
        } else {
            return MediaType::DOCUMENT;
        }
    }

    /**
     * Process image file (generate thumbnail, extract dimensions, etc.)
     */
    private function processImage(ArticleMedia $media, $file): void
    {
        try {
            // Extraire les dimensions de l'image
            list($width, $height) = getimagesize($file->getPathname());
            $media->dimensions = ['width' => $width, 'height' => $height];
            
            // Générer une miniature (implémentation simplifiée)
            // En production, utiliser une bibliothèque comme Intervention Image
            $thumbnailPath = 'thumbnails/' . $media->path;
            
            // Simuler la création d'une miniature
            // En réalité, vous utiliseriez Intervention Image ou une autre bibliothèque
            // pour créer et enregistrer la miniature
            // Storage::disk('public')->put($thumbnailPath, $thumbnailContent);
            
            $media->thumbnail_path = $thumbnailPath;
            $media->thumbnail_url = Storage::disk('public')->url($thumbnailPath);
        } catch (\Exception $e) {
            // Loguer l'erreur mais ne pas empêcher la création du média
            logger()->error('Erreur lors du traitement de l\'image: ' . $e->getMessage());
        }
    }

    /**
     * Process video file (extract metadata, generate thumbnail, etc.)
     */
    private function processVideo(ArticleMedia $media, $file): void
    {
        try {
            // Extraire les métadonnées de la vidéo
            // En production, utiliser une bibliothèque comme FFmpeg
            $media->meta = [
                'duration' => 0, // Récupérer la durée réelle avec FFmpeg
                'bitrate' => 0,  // Récupérer le bitrate réel avec FFmpeg
            ];
            
            // Générer une miniature (implémentation simplifiée)
            // En production, utiliser FFmpeg pour extraire une frame
            $thumbnailPath = 'thumbnails/' . pathinfo($media->path, PATHINFO_FILENAME) . '.jpg';
            
            // Simuler la création d'une miniature
            // Storage::disk('public')->put($thumbnailPath, $thumbnailContent);
            
            $media->thumbnail_path = $thumbnailPath;
            $media->thumbnail_url = Storage::disk('public')->url($thumbnailPath);
        } catch (\Exception $e) {
            // Loguer l'erreur mais ne pas empêcher la création du média
            logger()->error('Erreur lors du traitement de la vidéo: ' . $e->getMessage());
        }
    }
}
