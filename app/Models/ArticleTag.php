<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ArticleTag extends Pivot
{
    protected $table = 'article_tags';

    // Ton pivot a une colonne id auto-incrémentée
    public $incrementing = true;
    protected $primaryKey = 'id';

    protected $fillable = [
        'tenant_id', 'article_id', 'tag_id', 'sort_order',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'article_id' => 'integer',
        'tag_id' => 'integer',
        'sort_order' => 'integer',
    ];
}
