<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgNode extends Model
{
    protected $table = 'org_nodes';

    protected $fillable = [
        'user_id',
        'parent_id',
        'title',
        'department',
        'badge',
        'subtitle',
        'bio',
        'level',
        'accent',
        'sort_order',
        'pos_x',
        'pos_y',
        'is_active',
        'avatar_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'sort_order' => 'integer',
        'pos_x' => 'integer',
        'pos_y' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
