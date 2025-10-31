<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo, BelongsToMany, HasMany
};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'meta',
        'sort_order',
        'is_active',
        'is_featured',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];
  protected $dates = ['deleted_at']; // ✅ important pour que deleted_at soit reconnu

    protected $attributes = [
        'is_active' => true,
        'is_featured' => false,
        'sort_order' => 0,
    ];

    /* =======================================================
     | RELATIONS
     ======================================================= */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_categories')
            ->withPivot('is_primary', 'sort_order')
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /* =======================================================
     | SCOPES
     ======================================================= */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    public function scopeRoot(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function scopeByTenant(Builder $query, ?int $tenantId): void
    {
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /* =======================================================
     | ACCESSORS & MUTATORS
     ======================================================= */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value,
            set: fn($value) => ucfirst(trim($value))
        );
    }

    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value,
            set: fn($value, $attributes) =>
                Str::slug($value ?: ($attributes['name'] ?? ''))
        );
    }

    /* =======================================================
     | METHODES UTILES
     ======================================================= */
    public function getFullPath(): string
    {
        $path = [$this->name];
        $parent = $this->parent;
        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }
        return implode(' > ', $path);
    }

    public function getArticlesCount(): int
    {
        return $this->articles()->count();
    }

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function getChildrenCount(): int
    {
        return $this->children()->count();
    }

    public function getLevel(): int
    {
        $level = 0;
        $parent = $this->parent;
        while ($parent) {
            $level++;
            $parent = $parent->parent;
        }
        return $level;
    }

    /* =======================================================
     | EVENTS AUTOMATIQUES
     ======================================================= */
    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            // UUID automatique
            if (empty($category->uuid)) {
                $category->uuid = (string) Str::uuid();
            }

            // Slug automatique
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }

            // Assignation des auteurs + tenant
            if (Auth::check()) {
                $category->created_by = Auth::id();

                // Si ton modèle User a un champ tenant_id
                if (empty($category->tenant_id) && Auth::user()?->tenant_id) {
                    $category->tenant_id = Auth::user()->tenant_id;
                }
            }
        });

        static::updating(function (Category $category) {
            if ($category->isDirty('name')) {
                $category->slug = Str::slug($category->name);
            }

            if (Auth::check()) {
                $category->updated_by = Auth::id();
            }
        });

        static::deleted(function (Category $category) {
            // Exemple de logique future : supprimer les sous-catégories
            if ($category->children()->exists()) {
                $category->children()->delete();
            }
        });
    }
}
