<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ArticleRating extends Model
{
    use HasFactory, SoftDeletes;

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
        'uuid', // <-- facultatif, on le remplit en creating
    ];

    protected $casts = [
        'criteria_ratings' => 'array',
        'rating'           => 'integer',
        'is_verified'      => 'boolean',
        'is_helpful'       => 'boolean',
        'helpful_count'    => 'integer',
        'not_helpful_count'=> 'integer',
        'moderated_at'     => 'datetime',
    ];

    protected $attributes = [
        'rating'          => 5,
        'is_verified'     => false,
        'is_helpful'      => false,
        'helpful_count'   => 0,
        'not_helpful_count'=> 0,
        'status'          => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (ArticleRating $rating) {
            if (empty($rating->uuid)) {
                $rating->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relations
    public function article(): BelongsTo { return $this->belongsTo(Article::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function moderatedBy(): BelongsTo { return $this->belongsTo(User::class, 'moderated_by'); }

    // (… garde le reste de tes méthodes/utilitaires inchangés …)

    // Accessors/Mutators principaux (ex. pour rating) :
    protected function rating(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value,
            set: fn (int $value) => max(1, min(5, $value)),
        );
    }

    protected function guestName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v,
            set: fn (?string $v) => $v ? ucfirst(trim($v)) : null,
        );
    }

    protected function guestEmail(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v,
            set: fn (?string $v) => $v ? strtolower(trim($v)) : null,
        );
    }
}
