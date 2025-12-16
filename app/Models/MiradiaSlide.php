<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MiradiaSlide extends Model
{
    protected $fillable = [
        'title',
        'description',
        'stat_label',
        'tag',
        'icon',
        'color',
        'image_path',
        'position',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        // On suppose que tu utilises le disque "public"
        return Storage::disk('public')->url($this->image_path);
    }
}
