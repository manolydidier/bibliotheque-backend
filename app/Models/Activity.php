<?php
// app/Models/Activity.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = [
        'recipient_id','type','title','article_slug','comment_id','url','meta',
    ];
    protected $casts = ['meta' => 'array'];
}
