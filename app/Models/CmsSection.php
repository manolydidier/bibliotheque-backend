<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CmsSection extends Model
{
    protected $table = 'cms_sections';

    protected $fillable = [
        // 'tenant_id', // ❌ retiré temporairement

        'category',
        'title',
        'template',
        'section',
        'locale',

        'gjs_project',
        'html',
        'css',
        'js',

        'status',
        'published_at',
        'scheduled_at',

        'version',
        'sort_order',
        'meta',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'version' => 'integer',
        'sort_order' => 'integer',
        // 'tenant_id' => 'integer', // ❌ retiré temporairement
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /* ========= STATUSES ========= */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';

    public static function allowedStatuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_PUBLISHED];
    }

    /* ========= SCOPES ========= */
    // ❌ scopeForTenant retiré temporairement
    // public function scopeForTenant(Builder $q, int $tenantId): Builder
    // {
    //     return $q->where('tenant_id', $tenantId);
    // }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED)
                 ->whereNotNull('published_at');
    }

    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_DRAFT);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    /* ========= HELPERS ========= */
    public function publish(?\DateTimeInterface $at = null): void
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->published_at = $at ?? now();
        $this->scheduled_at = null;
    }

    public function unpublish(): void
    {
        $this->status = self::STATUS_DRAFT;
        $this->published_at = null;
        $this->scheduled_at = null;
    }

    public function schedule(\DateTimeInterface $when): void
    {
        $this->status = self::STATUS_PENDING;
        $this->scheduled_at = $when;
        $this->published_at = null;
    }

    public function isSlot(string $template, string $section, string $locale): bool
    {
        return $this->template === $template && $this->section === $section && $this->locale === $locale;
    }
}
