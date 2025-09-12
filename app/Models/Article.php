<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'featured_image_alt',
        'meta',
        'seo_data',
        'status',
        'visibility',
        'password',
        'published_at',
        'scheduled_at',
        'expires_at',
        'reading_time',
        'word_count',
        'view_count',
        'share_count',
        'comment_count',
        'rating_average',
        'rating_count',
        'is_featured',
        'is_sticky',
        'allow_comments',
        'allow_sharing',
        'allow_rating',
        'author_name',
        'author_bio',
        'author_avatar',
        'author_id',
        'created_by',
        'updated_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'uuid', // on garde la colonne uuid, mais ce n'est PAS la clé primaire
    ];

    // Clé primaire = entier auto-increment (par défaut), donc pas besoin de HasUuids
    protected $casts = [
        'meta' => 'array',
        'seo_data' => 'array',
        'status' => ArticleStatus::class,
        'visibility' => ArticleVisibility::class,
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'expires_at' => 'datetime',
        'reading_time' => 'integer',
        'word_count' => 'integer',
        'view_count' => 'integer',
        'share_count' => 'integer',
        'comment_count' => 'integer',
        'rating_average' => 'decimal:2',
        'rating_count' => 'integer',
        'is_featured' => 'boolean',
        'is_sticky' => 'boolean',
        'allow_comments' => 'boolean',
        'allow_sharing' => 'boolean',
        'allow_rating' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => ArticleStatus::DRAFT,
        'visibility' => ArticleVisibility::PUBLIC,
        'is_featured' => false,
        'is_sticky' => false,
        'allow_comments' => true,
        'allow_sharing' => true,
        'allow_rating' => true,
        'view_count' => 0,
        'share_count' => 0,
        'comment_count' => 0,
        'rating_average' => 0.00,
        'rating_count' => 0,
    ];

    // ---------------------------
    // Relations
    // ---------------------------
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'article_categories')
            ->withPivot('is_primary', 'sort_order')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tags')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
    
    public function media(): HasMany
    {
        return $this->hasMany(ArticleMedia::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->hasMany(Comment::class)->where('status', 'approved');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(ArticleRating::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ArticleShare::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ArticleHistory::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ---------------------------
    // Scopes
    // ---------------------------
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ArticleStatus::PUBLISHED)
              ->where('published_at', '<=', now())
              ->where(function ($q) {
                  $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
              });
    }

    public function scopePublic(Builder $query): void
    {
        $query->where('visibility', ArticleVisibility::PUBLIC);
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    public function scopeSticky(Builder $query): void
    {
        $query->where('is_sticky', true);
    }

    public function scopeByCategory(Builder $query, int $categoryId): void
    {
        $query->whereHas('categories', fn($q) => $q->where('categories.id', $categoryId));
    }

    public function scopeByTag(Builder $query, int $tagId): void
    {
        $query->whereHas('tags', fn($q) => $q->where('tags.id', $tagId));
    }

    public function scopeByAuthor(Builder $query, int $authorId): void
    {
        $query->where('author_id', $authorId);
    }

    public function scopeByTenant(Builder $query, ?int $tenantId): void
    {
        $query->where('tenant_id', $tenantId);
    }

    public function scopeSearch(Builder $query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('excerpt', 'like', "%{$search}%")
              ->orWhere('content', 'like', "%{$search}%");
        });
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('is_sticky', 'desc')
              ->orderBy('is_featured', 'desc')
              ->orderBy('published_at', 'desc');
    }

    // ---------------------------
    // Accessors & Mutators
    // ---------------------------
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value) => Str::slug($value),
        );
    }

    protected function title(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value) => ucfirst(trim($value)),
        );
    }

    protected function excerpt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: function (?string $value) {
                if (empty($value) && !empty($this->content)) {
                    return \Illuminate\Support\Str::limit(strip_tags($this->content), 160);
                }
                return $value;
            },
        );
    }

    // ---------------------------
    // Helpers
    // ---------------------------
    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::PUBLISHED
            && $this->published_at
            && $this->published_at->isPast()
            && (!$this->expires_at || $this->expires_at->isFuture());
    }

    public function isScheduled(): bool
    {
        return $this->status === ArticleStatus::PENDING
            && $this->scheduled_at
            && $this->scheduled_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isPublic(): bool
    {
        return $this->visibility === ArticleVisibility::PUBLIC;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === ArticleVisibility::PRIVATE;
    }

    public function isPasswordProtected(): bool
    {
        return $this->visibility === ArticleVisibility::PASSWORD_PROTECTED;
    }

    public function canBeViewedBy(?User $user): bool
    {
        if ($this->isPublic()) return true;
        if ($this->isPrivate() && !$user) return false;
        if ($this->isPasswordProtected()) return true; // à adapter
        return false;
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
        Cache::forget("article_{$this->id}_views");
    }

    public function incrementShareCount(): void
    {
        $this->increment('share_count');
    }

    public function incrementCommentCount(): void
    {
        $this->increment('comment_count');
    }

    public function updateRatingStats(): void
    {
        $ratingStats = $this->ratings()
            ->where('status', 'approved')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_ratings')
            ->first();

        $this->update([
            'rating_average' => $ratingStats->avg_rating ?? 0.00,
            'rating_count'   => $ratingStats->total_ratings ?? 0,
        ]);
    }

    public function calculateReadingTime(): int
    {
        $wordsPerMinute = 200;
        $wordCount = str_word_count(strip_tags($this->content));
        return max(1, (int) round($wordCount / $wordsPerMinute));
        // on pourrait aussi écrire en base si besoin
    }

    public function calculateWordCount(): int
    {
        return str_word_count(strip_tags($this->content));
    }

    public function getPrimaryCategory(): ?Category
    {
        return $this->categories()->wherePivot('is_primary', true)->first();
    }

    public function getUrl(): string
    {
             return url("/api/articles/{$this->slug}");
    }

    public function getFeaturedImageUrl(): ?string
    {
        return $this->featured_image ? asset('storage/' . $this->featured_image) : null;
    }

    public function getAuthorDisplayName(): string
    {
        return $this->author?->name ?? ($this->author_name ?: 'Anonyme');
    }

    public function getAuthorAvatarUrl(): ?string
    {
        if ($this->author?->profile_photo_url) {
            return $this->author->profile_photo_url;
        }
        return $this->author_avatar ? asset('storage/' . $this->author_avatar) : null;
    }

    // ---------------------------
    // Events
    // ---------------------------
    protected static function booted(): void
    {
        static::creating(function (Article $article) {
            // Génère un uuid si absent (et on NE TOUCHE PAS à id)
            if (empty($article->uuid)) {
                $article->uuid = (string) Str::uuid();
            }

            if (empty($article->slug) && !empty($article->title)) {
                $article->slug = Str::slug($article->title);
            }

            if (empty($article->excerpt) && !empty($article->content)) {
                $article->excerpt = \Illuminate\Support\Str::limit(strip_tags($article->content), 160);
            }

            $article->word_count   = $article->calculateWordCount();
            $article->reading_time = $article->calculateReadingTime();
        });

        static::updating(function (Article $article) {
            if ($article->isDirty('title') && empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }

            if ($article->isDirty('content')) {
                $article->word_count   = $article->calculateWordCount();
                $article->reading_time = $article->calculateReadingTime();
            }
        });

        static::saved(function (Article $article) {
            Cache::forget("article_{$article->id}");
            Cache::forget("article_{$article->slug}");
        });
    }
}
