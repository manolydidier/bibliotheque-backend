<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'article_id',
        'parent_id',
        'user_id',
        'guest_name',
        'guest_email',
        'guest_website',
        'content',
        'status',
        'meta',
        'like_count',
        'dislike_count',
        'reply_count',
        'is_featured',
        'moderated_by',
        'moderated_at',
        'moderation_notes',
        'created_by',
        'updated_by',
        // 'uuid' est généré automatiquement dans booted()
    ];

    protected $casts = [
        'meta' => 'array',
        'like_count' => 'integer',
        'dislike_count' => 'integer',
        'reply_count' => 'integer',
        'is_featured' => 'boolean',
        'moderated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'like_count' => 0,
        'dislike_count' => 0,
        'reply_count' => 0,
        'is_featured' => false,
    ];

    // Relationships
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
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
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending'); // ✅ correction
    }

    public function scopeRejected(Builder $query): void
    {
        $query->where('status', 'rejected');
    }

    public function scopeSpam(Builder $query): void
    {
        $query->where('status', 'spam');
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
        $query->where('tenant_id', $tenantId);
    }

    public function scopeByArticle(Builder $query, int $articleId): void
    {
        $query->where('article_id', $articleId);
    }

    public function scopeByUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('is_featured', 'desc')
              ->orderBy('created_at', 'asc');
    }

    // Accessors & Mutators
    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: fn (string $value) => trim($value),
        );
    }

    protected function guestName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: fn (?string $value) => $value ? ucfirst(trim($value)) : null,
        );
    }

    protected function guestEmail(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: fn (?string $value) => $value ? strtolower(trim($value)) : null,
        );
    }

    // Methods
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isSpam(): bool
    {
        return $this->status === 'spam';
    }

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    public function hasReplies(): bool
    {
        return $this->replies()->exists();
    }

    public function getRepliesCount(): int
    {
        return $this->replies()->count();
    }

    public function getAuthorName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        return $this->guest_name ?: 'Anonyme';
    }

    public function getAuthorEmail(): ?string
    {
        if ($this->user) {
            return $this->user->email;
        }
        return $this->guest_email;
    }

    public function getAuthorAvatar(): ?string
    {
        if ($this->user && $this->user->profile_photo_url) {
            return $this->user->profile_photo_url;
        }
        return null;
    }

    public function getDisplayName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        return $this->guest_name ?: 'Anonyme';
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

    public function canBeRepliedTo(): bool
    {
        return $this->isApproved() && $this->getLevel() < 3; // Max 3 niveaux
    }

    public function canBeEditedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        if ($this->user_id === $user->id) {
            return $this->created_at->diffInHours(now()) <= 1;
        }
        return $user->can('moderate comments');
    }

    public function canBeDeletedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        if ($this->user_id === $user->id) {
            return $this->created_at->diffInHours(now()) <= 1;
        }
        return $user->can('moderate comments');
    }

    public function approve(User $moderator, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
            'moderation_notes' => $notes,
        ]);

        // Update article comment count
        if ($this->relationLoaded('article') || $this->article()->exists()) {
            if (method_exists($this->article, 'incrementCommentCount')) {
                $this->article->incrementCommentCount();
            } else {
                $this->article->increment('comment_count');
            }
        }
    }

    public function reject(User $moderator, string $notes): void
    {
        $this->update([
            'status' => 'rejected',
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
            'moderation_notes' => $notes,
        ]);
    }

    public function markAsSpam(User $moderator, ?string $notes = null): void
    {
        $this->update([
            'status' => 'spam',
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
            'moderation_notes' => $notes,
        ]);
    }

    public function incrementLikeCount(): void
    {
        $this->increment('like_count');
    }

    public function decrementLikeCount(): void
    {
        if ($this->like_count > 0) {
            $this->decrement('like_count');
        }
    }

    public function incrementDislikeCount(): void
    {
        $this->increment('dislike_count');
    }

    public function decrementDislikeCount(): void
    {
        if ($this->dislike_count > 0) {
            $this->decrement('dislike_count');
        }
    }

    public function incrementReplyCount(): void
    {
        $this->increment('reply_count');
    }

    public function decrementReplyCount(): void
    {
        if ($this->reply_count > 0) {
            $this->decrement('reply_count');
        }
    }

    public function getTotalVotes(): int
    {
        return $this->like_count + $this->dislike_count;
    }

    public function getVoteRatio(): float
    {
        if ($this->getTotalVotes() === 0) {
            return 0;
        }
        return round($this->like_count / $this->getTotalVotes(), 2);
    }

    // Events
    protected static function booted(): void
    {
        static::creating(function (Comment $comment) {
            // Génère une valeur pour la colonne 'uuid' (pas la PK)
            if (empty($comment->uuid)) {
                $comment->uuid = (string) Str::uuid();
            }
            if (Auth::check()) {
                $comment->user_id    = Auth::id();
                $comment->created_by = Auth::id();

                // Duplique le nom/email si non fournis
                $comment->guest_name  = $comment->guest_name  ?? (Auth::user()->name  ?? null);
                $comment->guest_email = $comment->guest_email ?? (Auth::user()->email ?? null);
            }
        });

        static::updating(function (Comment $comment) {
            if (Auth::check()) {
                $comment->updated_by = Auth::id();
            }
        });

        static::deleted(function (Comment $comment) {
            // Update article comment count
            if ($comment->isApproved() && $comment->article) {
                if (method_exists($comment->article, 'decrementCommentCount')) {
                    $comment->article->decrementCommentCount();
                } else {
                    $comment->article->decrement('comment_count');
                }
            }
        });
    }
}
