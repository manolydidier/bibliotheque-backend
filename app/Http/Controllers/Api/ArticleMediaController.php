<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArticleMedia;
use App\Enums\MediaType;
use App\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Gd\Driver;

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
            $val = strtolower((string) $request->type);
            if ($type = MediaType::tryFrom($val)) {
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

        // Corbeille : supporte ?trashed=only|with  (sinon rétro-compat only_trashed/with_trashed)
        if ($request->has('trashed')) {
            $trashed = $request->get('trashed');
            if ($trashed === 'only') {
                $query->onlyTrashed();
            } elseif ($trashed === 'with') {
                $query->withTrashed();
            }
        } else {
            if ($request->boolean('only_trashed')) {
                $query->onlyTrashed();
            } elseif ($request->boolean('with_trashed')) {
                $query->withTrashed();
            }
        }

        // Tri
        $sortField = $request->get('sort_by', 'sort_order');
        $sortDir   = $request->get('sort_dir', 'asc');
        if (in_array($sortField, ['name', 'sort_order', 'created_at', 'size'], true)) {
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
       HELPER: Normalisation des chemins
       =======================*/
    private function toPublicDiskRelative(?string $p): ?string
    {
        if (!$p) return null;
        $s = trim($p);

        // URL complète -> extrait la partie après /storage/
        if (preg_match('#https?://[^/]+/(.*)$#i', $s, $m)) {
            $s = $m[1];
        }

        // enlève les slashs en tête
        $s = ltrim($s, '/');

        // si commence par "storage/", on l'enlève (car le DISK "public" pointe sur storage/app/public)
        if (str_starts_with($s, 'storage/')) {
            $s = substr($s, strlen('storage/'));
        }

        // si commence par "public/", on l'enlève aussi (rare mais possible)
        if (str_starts_with($s, 'public/')) {
            $s = substr($s, strlen('public/'));
        }

        return ltrim($s, '/');
    }

    /* =======================
       DELETE (soft delete)
       =======================*/
    public function destroy(string $id): JsonResponse
    {
        $media = ArticleMedia::find($id);
        if (!$media) {
            return response()->json(['message' => 'Média non trouvé'], 404);
        }

        try {
            DB::transaction(function () use ($media) {
                $disk = Storage::disk('public');

                $trashBase = "articles/{$media->article_id}/media/_trash";
                $disk->makeDirectory($trashBase);
                $disk->makeDirectory($trashBase . '/_thumbs');

                $meta = (array) ($media->meta ?? []);
                $meta['original_path']            = $media->path ?? null;
                $meta['original_thumbnail_path']  = $media->thumbnail_path ?? null;

                // --- normalise les chemins AVANT exists/move ---
                $relPath      = $this->toPublicDiskRelative($media->path);
                $relThumbPath = $this->toPublicDiskRelative($media->thumbnail_path);

                // -------- FICHIER PRINCIPAL --------
                if ($relPath && $disk->exists($relPath)) {
                    $newPath = $trashBase . '/' . basename($relPath);
                    if ($disk->exists($newPath)) {
                        $newPath = $trashBase . '/' . uniqid('', true) . '-' . basename($relPath);
                    }
                    $disk->move($relPath, $newPath);
                    $media->path = $newPath;
                    $media->url  = $disk->url($newPath);
                    
                    logger()->info('ArticleMedia destroy(): fichier principal déplacé vers corbeille', [
                        'id' => $media->id,
                        'from' => $relPath,
                        'to' => $newPath
                    ]);
                } else {
                    logger()->warning('ArticleMedia destroy(): fichier introuvable pour move', [
                        'id' => $media->id,
                        'path' => $media->path,
                        'rel' => $relPath
                    ]);
                }

                // -------- MINIATURE --------
                if ($relThumbPath && $disk->exists($relThumbPath)) {
                    $newThumbPath = $trashBase . '/_thumbs/' . basename($relThumbPath);
                    if ($disk->exists($newThumbPath)) {
                        $newThumbPath = $trashBase . '/_thumbs/' . uniqid('', true) . '-' . basename($relThumbPath);
                    }
                    $disk->move($relThumbPath, $newThumbPath);
                    $media->thumbnail_path = $newThumbPath;
                    $media->thumbnail_url  = $disk->url($newThumbPath);
                    
                    logger()->info('ArticleMedia destroy(): miniature déplacée vers corbeille', [
                        'id' => $media->id,
                        'from' => $relThumbPath,
                        'to' => $newThumbPath
                    ]);
                } else {
                    if ($relThumbPath) {
                        logger()->warning('ArticleMedia destroy(): miniature introuvable pour move', [
                            'id' => $media->id,
                            'thumb' => $media->thumbnail_path,
                            'rel' => $relThumbPath
                        ]);
                    }
                }

                $media->meta = $meta;

                // Soft delete (remplit deleted_at) après avoir sauvé les nouveaux chemins
                $media->save();
                $media->delete();
            });

            return response()->json(['message' => 'Média déplacé en corbeille (soft-delete)']);
        } catch (\Throwable $e) {
            logger()->error('ArticleMedia destroy(): erreur lors de la mise en corbeille', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la mise en corbeille',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* =======================
       RESTORE
       =======================*/
    public function restore(string $id): JsonResponse
    {
        /** @var ArticleMedia|null $media */
        $media = ArticleMedia::onlyTrashed()->find($id);
        if (!$media) {
            return response()->json(['message' => 'Média non trouvé en corbeille'], 404);
        }

        try {
            DB::transaction(function () use ($media) {
                $disk = Storage::disk('public');
                $meta = (array) ($media->meta ?? []);

                // Chemins d'origine mémorisés lors du destroy()
                $origPath = $meta['original_path'] ?? null;
                $origThumbPath = $meta['original_thumbnail_path'] ?? null;

                // -------- RESTAURER LE FICHIER PRINCIPAL --------
                if ($origPath && $media->path && $disk->exists($media->path)) {
                    // S'assure que le dossier d'origine existe
                    $disk->makeDirectory(dirname($origPath));
                    
                    // Collision éventuelle => suffixe
                    $finalPath = $origPath;
                    if ($disk->exists($finalPath)) {
                        $finalPath = dirname($origPath) . '/' . uniqid('', true) . '-' . basename($origPath);
                    }
                    
                    $disk->move($media->path, $finalPath);
                    $media->path = $finalPath;
                    $media->url  = $disk->url($finalPath);
                    
                    logger()->info('ArticleMedia restore(): fichier principal restauré', [
                        'id' => $media->id,
                        'from' => $media->path,
                        'to' => $finalPath
                    ]);
                }

                // -------- RESTAURER LA MINIATURE --------
                if ($origThumbPath && $media->thumbnail_path && $disk->exists($media->thumbnail_path)) {
                    $disk->makeDirectory(dirname($origThumbPath));
                    
                    $finalThumb = $origThumbPath;
                    if ($disk->exists($finalThumb)) {
                        $finalThumb = dirname($origThumbPath) . '/' . uniqid('', true) . '-' . basename($origThumbPath);
                    }
                    
                    $disk->move($media->thumbnail_path, $finalThumb);
                    $media->thumbnail_path = $finalThumb;
                    $media->thumbnail_url  = $disk->url($finalThumb);
                    
                    logger()->info('ArticleMedia restore(): miniature restaurée', [
                        'id' => $media->id,
                        'from' => $media->thumbnail_path,
                        'to' => $finalThumb
                    ]);
                }

                // Nettoie les meta "original_*"
                unset($meta['original_path']);
                unset($meta['original_thumbnail_path']);
                $media->meta = $meta;

                $media->restore(); // enlève deleted_at
                $media->save();
            });

            return response()->json([
                'message' => 'Média restauré avec succès',
                'data' => $media->load(['article', 'createdBy', 'updatedBy'])
            ]);
        } catch (\Throwable $e) {
            logger()->error('ArticleMedia restore(): erreur lors de la restauration', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la restauration',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* =======================
       FORCE DELETE (suppression définitive)
       =======================*/
    public function forceDelete(string $id): JsonResponse
    {
        $media = ArticleMedia::onlyTrashed()->find($id);
        if (!$media) {
            return response()->json(['message' => 'Média supprimé non trouvé'], 404);
        }

        try {
            DB::transaction(function () use ($media) {
                $disk = Storage::disk('public');
                
                // Normaliser les chemins
                $relPath = $this->toPublicDiskRelative($media->path);
                $relThumbPath = $this->toPublicDiskRelative($media->thumbnail_path);
                
                // -------- SUPPRIMER LE FICHIER PRINCIPAL (même dans _trash) --------
                if ($relPath && $disk->exists($relPath)) {
                    $disk->delete($relPath);
                    logger()->info('ArticleMedia forceDelete(): fichier principal supprimé définitivement', [
                        'id' => $media->id,
                        'path' => $relPath
                    ]);
                } else {
                    logger()->warning('ArticleMedia forceDelete(): fichier principal introuvable', [
                        'id' => $media->id,
                        'path' => $media->path,
                        'rel' => $relPath
                    ]);
                }
                
                // -------- SUPPRIMER LA MINIATURE (même dans _trash) --------
                if ($relThumbPath && $disk->exists($relThumbPath)) {
                    $disk->delete($relThumbPath);
                    logger()->info('ArticleMedia forceDelete(): miniature supprimée définitivement', [
                        'id' => $media->id,
                        'thumbnail_path' => $relThumbPath
                    ]);
                } else {
                    if ($relThumbPath) {
                        logger()->warning('ArticleMedia forceDelete(): miniature introuvable', [
                            'id' => $media->id,
                            'thumbnail_path' => $media->thumbnail_path,
                            'rel' => $relThumbPath
                        ]);
                    }
                }
                
                // -------- SUPPRIMER L'ENREGISTREMENT EN BASE DE DONNÉES --------
                $media->forceDelete();
                
                logger()->info('ArticleMedia forceDelete(): enregistrement supprimé de la base de données', [
                    'id' => $media->id
                ]);
            });
            
            return response()->json(['message' => 'Média définitivement supprimé avec succès']);
        } catch (\Throwable $e) {
            logger()->error('ArticleMedia forceDelete(): erreur lors de la suppression définitive', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la suppression définitive du média',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* =======================
       UPLOAD (fichier)
       =======================*/
    public function upload(Request $request): JsonResponse
    {
        if (!$request->hasFile('file')) {
            return response()->json([
                'message' => "Aucun fichier reçu. Vérifiez 'upload_max_filesize' (".ini_get('upload_max_filesize').") et 'post_max_size' (".ini_get('post_max_size').")"
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'file'       => 'required|file|max:102400', // 100MB
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
            $val = strtolower((string) $request->type);
            if ($type = MediaType::tryFrom($val)) {
                $query->byType($type);
            }
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

        // Corbeille : même logique que index()
        if ($request->has('trashed')) {
            $trashed = $request->get('trashed');
            if ($trashed === 'only') {
                $query->onlyTrashed();
            } elseif ($trashed === 'with') {
                $query->withTrashed();
            }
        } else {
            if ($request->boolean('only_trashed')) {
                $query->onlyTrashed();
            } elseif ($request->boolean('with_trashed')) {
                $query->withTrashed();
            }
        }

        // Tri
        $sortField = $request->get('sort_by', 'sort_order');
        $sortDir   = $request->get('sort_dir', 'asc');
        if (in_array($sortField, ['name', 'sort_order', 'created_at', 'size'], true)) {
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
        // Ne fabrique une miniature que pour les images
        if (!str_starts_with((string) $media->mime_type, 'image/')) {
            $media->thumbnail_path = null;
            $media->thumbnail_url  = null;
            return;
        }

        try {
            // 1) Résoudre un chemin lisible
            $sourcePath = method_exists($file, 'getRealPath')
                ? $file->getRealPath()
                : (method_exists($file, 'getPathname') ? $file->getPathname() : (string) $file);

            if (!$sourcePath || !is_readable($sourcePath)) {
                throw new \RuntimeException('Chemin source illisible: '.$sourcePath);
            }

            // 2) Lire l'image (Intervention v3)
            $manager = new ImageManager(new GdDriver());
            $image   = $manager->read($sourcePath);

            // Dimensions d'origine (en DB)
            $media->dimensions = [
                'width'  => $image->width(),
                'height' => $image->height(),
            ];

            // 3) Dossier des vignettes dans le disk 'public'
            //    => storage/app/public/articles/{id}/media/_thumbs
            $thumbDir  = 'articles/'.$media->article_id.'/media/_thumbs';
            Storage::disk('public')->makeDirectory($thumbDir);

            $baseName  = pathinfo((string) $media->filename, PATHINFO_FILENAME); // uuid sans ext
            $thumbPath = $thumbDir.'/'.$baseName.'-thumb.jpg';

            // 4) Miniature: largeur max 480
            $thumb = (clone $image);
            $maxW  = 480;
            if ($thumb->width() > $maxW) {
                $thumb = $thumb->scale(width: $maxW); // conserve le ratio
            }

            // 5) Sauvegarde JPG qualité 80 sur le disk 'public'
            Storage::disk('public')->put($thumbPath, (string) $thumb->toJpeg(80));

            // Optionnel: vérifier que le fichier existe bien
            if (!Storage::disk('public')->exists($thumbPath)) {
                throw new \RuntimeException('Échec d\'écriture de la miniature: '.$thumbPath);
            }

            // 6) Enregistrer en DB (privilégie *path*, le front reconstruit l'URL)
            $media->thumbnail_path = $thumbPath;
            $media->thumbnail_url  = Storage::disk('public')->url($thumbPath);

        } catch (\Throwable $e) {
            logger()->warning('processImage error: '.$e->getMessage());
            $media->thumbnail_path = null;
            $media->thumbnail_url  = null;
        }
    }

    private function processVideo(ArticleMedia $media, $file): void
    {
        try {
            $media->meta = ['duration' => 0, 'bitrate' => 0];
            $thumbPath = 'thumbnails/'.pathinfo($media->path, PATHINFO_FILENAME).'.jpg';
            $media->thumbnail_path = $thumbPath;
            $media->thumbnail_url  = Storage::disk('public')->url($thumbPath);
        } catch (\Throwable $e) {
            logger()->warning('processVideo error: '.$e->getMessage());
        }
    }
}