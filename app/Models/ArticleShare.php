<?php

namespace App\Models;

use App\Enums\ShareMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// use Illuminate\Database\Eloquent\Concerns\HasUuids; // inutile si on gère nous-mêmes uuid
use Illuminate\Support\Str;

class ArticleShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'article_id',
        'user_id',
        'method',
        'platform',
        'url',
        'meta',
        'ip_address',
        'user_agent',
        'referrer',
        'location',
        'is_converted',
        'converted_at',
        'uuid',
    ];

    protected $casts = [
        'meta'         => 'array',
        'location'     => 'array',
        'is_converted' => 'boolean',
        'converted_at' => 'datetime',
        'method'       => ShareMethod::class,
    ];

    protected $attributes = [
        'is_converted' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (ArticleShare $share) {
            if (empty($share->uuid)) {
                $share->uuid = (string) Str::uuid();
            }
        });
    }

    // /s/{share} utilisera la colonne 'uuid'
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMethodLabel(): string
    {
        return $this->method->label();
    }

    public function getPlatformLabel(): string
    {
        return match($this->platform) {
            'facebook' => 'Facebook',
            'twitter'  => 'Twitter',
            'linkedin' => 'LinkedIn',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'email'    => 'Email',
            'link'     => 'Lien direct',
            'embed'    => 'Intégration',
            'print'    => 'Impression',
            default    => ucfirst($this->platform ?? ''),
        };
    }

    public function getLocationCountry(): ?string { return $this->location['country'] ?? null; }
    public function getLocationCity(): ?string    { return $this->location['city'] ?? null; }
    public function getLocationRegion(): ?string  { return $this->location['region'] ?? null; }

    public function markAsConverted(): void
    {
        $this->update([
            'is_converted' => true,
            'converted_at' => now(),
        ]);
    }

    public function isConverted(): bool
    {
        return $this->is_converted;
    }

    public function getConversionTime(): ?int
    {
        if ($this->is_converted && $this->converted_at) {
            return $this->created_at->diffInMinutes($this->converted_at);
        }
        return null;
    }

    // ⬇️ NE PAS MODIFIER, tu l'as demandé
    public function getShareUrl(): string
    {
        if ($this->url) {
            return $this->url;
        }

        $articleUrl = $this->article->getUrl();

        return match($this->method) {
            ShareMethod::EMAIL => "mailto:?subject=" . urlencode($this->article->title) . "&body=" . urlencode($articleUrl),
            ShareMethod::SOCIAL => $this->getSocialShareUrl($articleUrl),
            ShareMethod::LINK => $articleUrl,
            ShareMethod::EMBED => $articleUrl,
            ShareMethod::PRINT => "javascript:window.print()",
            default => $articleUrl,
        };
    }

    private function getSocialShareUrl(string $articleUrl): string
    {
        $title   = urlencode($this->article->title);
        $excerpt = urlencode($this->article->excerpt ?? '');

        return match($this->platform) {
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u=" . urlencode($articleUrl),
            'twitter'  => "https://twitter.com/intent/tweet?text={$title}&url=" . urlencode($articleUrl),
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url=" . urlencode($articleUrl),
            'whatsapp' => "https://wa.me/?text={$title}%20" . urlencode($articleUrl),
            'telegram' => "https://t.me/share/url?url=" . urlencode($articleUrl) . "&text={$title}",
            default    => $articleUrl,
        };
    }
}
