<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bureau extends Model
{
    use HasFactory;

    protected $table = 'bureaux';

    protected $fillable = [
        'societe_id',
        'name',
        'type',
        'city',
        'country',
        'address',
        'latitude',
        'longitude',
        'phone',
        'email',
        'image_url',
        'is_primary',
        'is_active',
    ];

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        if ($tenantId) {
            $query->whereHas('societe', fn ($q) => $q->where('id', $tenantId));
        }

        return $query;
    }
}
