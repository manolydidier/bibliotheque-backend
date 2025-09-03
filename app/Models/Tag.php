<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'description',
        'color',
        'meta',
        'usage_count',
        'is_active',
        'created_by',
        'updated_by',
    ];
    

    protected $casts = [
        'meta' => 'array',
        'usage_count' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
        'usage_count' => 0,
    ];

    // Relationships
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_tags')
            ->withPivot('sort_order')
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

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopePopular(Builder $query, int $limit = 10): void
    {
        $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    public function scopeByTenant(Builder $query, ?int $tenantId): void
    {
        $query->where('tenant_id', $tenantId);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('usage_count', 'desc')->orderBy('name');
    }

    // Accessors & Mutators
    // Accessors & Mutators corrigés
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value, // Supprimez le type string
            set: fn($value) => $value ? Str::slug($value) : null,
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value, // Supprimez le type string
            set: fn($value) => $value ? ucfirst(trim($value)) : null,
        );
    }

    // Methods
    public function incrementUsageCount(): void
    {
        $this->increment('usage_count');
    }

    public function decrementUsageCount(): void
    {
        if ($this->usage_count > 0) {
            $this->decrement('usage_count');
        }
    }

    public function getArticlesCount(): int
    {
        return $this->articles()->count();
    }

    public function isPopular(): bool
    {
        return $this->usage_count > 10; // Threshold for popular tags
    }

    // Events
    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
             if (empty($tag->uuid)) {
                $tag->uuid = Str::uuid()->toString();
            }

            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });

        static::updating(function (Tag $tag) {
            if ($tag->isDirty('name') && empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }
}
