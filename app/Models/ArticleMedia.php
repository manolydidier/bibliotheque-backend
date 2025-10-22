<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;

class ArticleMedia extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'article_id',
        'name',
        'filename',
        'original_filename',
        'path',
        'url',
        'thumbnail_path',
        'thumbnail_url',
        'type',
        'mime_type',
        'size',
        'dimensions',
        'meta',
        'alt_text',
        'caption',
        'sort_order',
        'is_featured',
        'is_active',
        'created_by',
        'updated_by',
        'uuid',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'meta' => 'array',
        'alt_text' => 'array',
        'size' => 'integer',
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'deleted_at'  => 'datetime',
        'type' => MediaType::class,
    ];

    protected $attributes = [
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 0,
        'size' => 0,
    ];

    // Relationships
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    public function scopeByType(Builder $query, MediaType $type): void
    {
        $query->where('type', $type);
    }

    public function scopeByTenant(Builder $query, ?int $tenantId): void
    {
        $query->where('tenant_id', $tenantId);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('created_at');
    }

    // Accessors & Mutators
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value) => ucfirst(trim($value)),
        );
    }

    protected function originalFilename(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value) => trim($value),
        );
    }

    // Methods
    public function getFullPath(): string
    {
        return Storage::disk('public')->path($this->path);
    }

    public function getFullUrl(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getThumbnailUrl(): ?string
    {
        if ($this->thumbnail_path) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }
        return null;
    }

    public function getFileSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDimensions(): array
    {
        if ($this->dimensions && isset($this->dimensions['width'], $this->dimensions['height'])) {
            return [
                'width' => $this->dimensions['width'],
                'height' => $this->dimensions['height'],
            ];
        }
        return [];
    }

    public function getWidth(): ?int
    {
        return $this->dimensions['width'] ?? null;
    }

    public function getHeight(): ?int
    {
        return $this->dimensions['height'] ?? null;
    }

    public function getAspectRatio(): ?float
    {
        if ($this->getWidth() && $this->getHeight()) {
            return round($this->getWidth() / $this->getHeight(), 2);
        }
        return null;
    }

    public function isImage(): bool
    {
        return $this->type === MediaType::IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->type === MediaType::VIDEO;
    }

    public function isAudio(): bool
    {
        return $this->type === MediaType::AUDIO;
    }

    public function isDocument(): bool
    {
        return $this->type === MediaType::DOCUMENT;
    }

    public function isEmbed(): bool
    {
        return $this->type === MediaType::EMBED;
    }

    public function getAltText(string $locale = 'fr'): ?string
    {
        if (is_array($this->alt_text)) {
            return $this->alt_text[$locale] ?? $this->alt_text['en'] ?? null;
        }
        return $this->alt_text;
    }

    public function getCaption(string $locale = 'fr'): ?string
    {
        if (is_array($this->caption)) {
            return $this->caption[$locale] ?? $this->caption['en'] ?? null;
        }
        return $this->caption;
    }

    public function getDuration(): ?string
    {
        if ($this->meta && isset($this->meta['duration'])) {
            $seconds = $this->meta['duration'];
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;

            if ($hours > 0) {
                return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
            }
            return sprintf('%02d:%02d', $minutes, $secs);
        }
        return null;
    }

    public function getBitrate(): ?string
    {
        if ($this->meta && isset($this->meta['bitrate'])) {
            $bitrate = $this->meta['bitrate'];
            if ($bitrate > 1000000) {
                return round($bitrate / 1000000, 1) . ' Mbps';
            }
            if ($bitrate > 1000) {
                return round($bitrate / 1000, 1) . ' Kbps';
            }
            return $bitrate . ' bps';
        }
        return null;
    }

    /**
     * Supprime les fichiers physiques
     * 
     * ⚠️ ATTENTION : Cette méthode ne doit être appelée que lors d'un forceDelete
     * Elle est maintenant gérée par ArticleMediaController::forceDelete()
     * 
     * @deprecated Utilisez ArticleMediaController::forceDelete()
     * @return bool
     */
    public function deleteFile(): bool
    {
        try {
            $disk = Storage::disk('public');
            
            // Normaliser le chemin
            $path = $this->normalizePath($this->path);
            $thumbPath = $this->normalizePath($this->thumbnail_path);
            
            // ⚠️ Ne supprimer que si les fichiers NE SONT PAS dans _trash
            if ($path && !str_contains($path, '/_trash/') && $disk->exists($path)) {
                $disk->delete($path);
                logger()->info('ArticleMedia deleteFile(): fichier principal supprimé', [
                    'id' => $this->id,
                    'path' => $path
                ]);
            }
            
            if ($thumbPath && !str_contains($thumbPath, '/_trash/') && $disk->exists($thumbPath)) {
                $disk->delete($thumbPath);
                logger()->info('ArticleMedia deleteFile(): miniature supprimée', [
                    'id' => $this->id,
                    'thumbnail_path' => $thumbPath
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            logger()->error('ArticleMedia deleteFile(): erreur', [
                'id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Normalise un chemin pour le disk 'public'
     * 
     * @param string|null $path
     * @return string|null
     */
    private function normalizePath(?string $path): ?string
    {
        if (!$path) return null;
        
        $s = trim($path);
        
        // Si c'est une URL complète, extraire la partie relative
        if (preg_match('#https?://[^/]+/(.*)$#i', $s, $m)) {
            $s = $m[1];
        }
        
        // Enlever les slashs en début
        $s = ltrim($s, '/');
        
        // Si commence par "storage/", l'enlever
        if (str_starts_with($s, 'storage/')) {
            $s = substr($s, strlen('storage/'));
        }
        
        // Si commence par "public/", l'enlever
        if (str_starts_with($s, 'public/')) {
            $s = substr($s, strlen('public/'));
        }
        
        return ltrim($s, '/');
    }

    /**
     * Vérifie si le média est dans la corbeille
     * 
     * @return bool
     */
    public function isInTrash(): bool
    {
        return $this->trashed() && (
            str_contains($this->path ?? '', '/_trash/') ||
            str_contains($this->thumbnail_path ?? '', '/_trash/')
        );
    }

    // Events
    protected static function booted(): void
    {
        // ✅ CORRECTION : Ne supprimer les fichiers que lors d'un forceDelete
        static::deleting(function (ArticleMedia $media) {
            // Vérifier si c'est un forceDelete (suppression définitive)
            if ($media->isForceDeleting()) {
                logger()->info('ArticleMedia booted deleting: forceDelete détecté', [
                    'id' => $media->id,
                    'path' => $media->path,
                    'in_trash' => $media->isInTrash()
                ]);
                
                // Ne rien faire ici, le contrôleur ArticleMediaController::forceDelete()
                // gère la suppression des fichiers de manière plus contrôlée
            } else {
                // C'est un soft delete
                logger()->info('ArticleMedia booted deleting: soft delete détecté, ne pas toucher aux fichiers', [
                    'id' => $media->id,
                    'path' => $media->path
                ]);
                
                // ⚠️ NE RIEN FAIRE : Le contrôleur ArticleMediaController::destroy()
                // déplace les fichiers dans _trash AVANT d'appeler delete()
            }
        });

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}