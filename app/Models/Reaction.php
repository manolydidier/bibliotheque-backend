<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    protected $fillable = [
        'user_id',
        'reactable_type',
        'reactable_id',
        'type',
    ];

    // relation vers user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // polymorphic parent
    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}
