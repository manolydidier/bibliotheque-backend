<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'email', 'username', 'password',
        'first_name', 'last_name', 'phone', 'address',
        'date_of_birth', 'avatar_url',
        'is_active', 'email_verified', 'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    // ✅ Utilisé automatiquement pour Auth::attempt
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    // ✅ Hachage automatique à l’enregistrement
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);

    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function getNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function permissions()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id')
            ->values();
    }

    public function canAccess(string $permissionName): bool
    {
        return $this->permissions()->contains('name', $permissionName);
    }
}