<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ArticleRating extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'tenant_id',
        'article_id',
        'user_id',
        'guest_email',
        'guest_name',
        'rating',
        'review',
        'criteria_ratings',
        'is_verified',
        'is_helpful',
        'helpful_count',
        'not_helpful_count',
        'status',
        'moderated_by',
        'moderated_at',
        'moderation_notes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'criteria_ratings' => 'array',
        'rating' => 'integer',
        'is_verified' => 'boolean',
        'is_helpful' => 'boolean',
        'helpful_count' => 'integer',
        'not_helpful_count' => 'integer',
        'moderated_at' => 'datetime',
    ];

    protected $attributes = [
        'rating' => 5,
        'is_verified' => false,
        'is_helpful' => false,
        'helpful_count' => 0,
        'not_helpful_count' => 0,
        'status' => 'pending',
    ];

    // Relationships
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
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

    public function getRatingStars(): string
    {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $this->rating) {
                $stars .= '★';
            } else {
                $stars .= '☆';
            }
        }
        return $stars;
    }

    public function getRatingPercentage(): float
    {
        return ($this->rating / 5) * 100;
    }

    public function getCriteriaRating(string $criteria): ?int
    {
        return $this->criteria_ratings[$criteria] ?? null;
    }

    public function getCriteriaRatings(): array
    {
        return $this->criteria_ratings ?? [];
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

    public function getTotalVotes(): int
    {
        return $this->helpful_count + $this->not_helpful_count;
    }

    public function getHelpfulRatio(): float
    {
        if ($this->getTotalVotes() === 0) {
            return 0;
        }
        return round($this->helpful_count / $this->getTotalVotes(), 2);
    }

    public function isHelpful(): bool
    {
        return $this->is_helpful;
    }

    public function markAsHelpful(): void
    {
        $this->increment('helpful_count');
        $this->update(['is_helpful' => true]);
    }

    public function markAsNotHelpful(): void
    {
        $this->increment('not_helpful_count');
        $this->update(['is_helpful' => false]);
    }

    public function approve(User $moderator, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
            'moderation_notes' => $notes,
        ]);

        // Update article rating stats
        $this->article->updateRatingStats();
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

    public function verify(): void
    {
        $this->update(['is_verified' => true]);
    }

    public function isVerified(): bool
    {
        return $this->is_verified;
    }

    public function getRatingLabel(): string
    {
        return match($this->rating) {
            1 => 'Très mauvais',
            2 => 'Mauvais',
            3 => 'Moyen',
            4 => 'Bon',
            5 => 'Excellent',
            default => 'Non évalué',
        };
    }

    public function getRatingColor(): string
    {
        return match($this->rating) {
            1, 2 => 'red',
            3 => 'yellow',
            4, 5 => 'green',
            default => 'gray',
        };
    }

    // Accessors & Mutators
    protected function rating(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value,
            set: function (int $value) {
                return max(1, min(5, $value)); // Ensure rating is between 1 and 5
            },
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
}
