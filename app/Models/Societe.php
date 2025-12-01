<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Societe extends Model
{
    use HasFactory;

    protected $table = 'societes';

    protected $fillable = [
         'name',
        'slug',
        'logo_url',
        'primary_color',
        'contact_email',
        'contact_phone',
        'website_url',
        'is_active',
        'responsable',
        'adresse',
        'ville',
        'pays',
        'description',
    ];

    // Relations
    public function bureaux()
    {
        return $this->hasMany(Bureau::class);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function articles()
    {
        return $this->hasMany(Article::class, 'tenant_id');
    }

   
     protected $casts = [
        'is_active' => 'boolean',
    ];

    // Pour que "statut" apparaisse dans le JSON renvoyé à React
    protected $appends = ['statut'];

    /** Scope existant  : utilisée dans ton index() */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Accessor : transforme is_active en string "active"/"inactive" */
    public function getStatutAttribute(): string
    {
        // si un jour tu ajoutes une vraie colonne `statut` en base,
        // tu pourras adapter ici
        return $this->is_active ? 'active' : 'inactive';
    }
}
