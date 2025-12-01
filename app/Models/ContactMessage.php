<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'subject',
        'type',
        'message',
        'consent',
        'company',
        'ip_address',
        'user_agent',
        'sent_to_email',
        'status',
    ];

    protected $casts = [
        'consent' => 'boolean',
    ];
}
