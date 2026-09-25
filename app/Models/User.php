<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_DELIVERY = 'delivery';

    public const ROLE_CLIENT = 'client';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Nom de la route de l'espace propre au rôle de l'utilisateur.
     */
    public function homeRoute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_DELIVERY => 'delivery.dashboard',
            default => 'home',
        };
    }

    /**
     * Commandes passées par l'utilisateur (rôle client).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    /**
     * Commandes assignées à l'utilisateur (rôle livreur).
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Order::class, 'delivery_id');
    }
}
