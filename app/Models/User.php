<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
        'account_status',
        'rejection_reason',
        'neighborhood_id',
        'address_landmarks',
        'approved_at',
        'approved_by',
        'blocked_until',
        'flagged_at',
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
            'role' => Role::class,
            'account_status' => AccountStatus::class,
            'approved_at' => 'datetime',
            'blocked_until' => 'datetime',
            'flagged_at' => 'datetime',
        ];
    }

    /**
     * Vérifie le rôle à partir de ses valeurs brutes (ex. paramètres du middleware "role").
     */
    public function hasRole(Role|string ...$roles): bool
    {
        $values = array_map(fn (Role|string $role) => $role instanceof Role ? $role->value : $role, $roles);

        return in_array($this->role?->value, $values, true);
    }

    public function isClient(): bool
    {
        return $this->role === Role::Client;
    }

    public function isDelivery(): bool
    {
        return $this->role === Role::Delivery;
    }

    public function isBusiness(): bool
    {
        return $this->role === Role::Business;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isApproved(): bool
    {
        return $this->account_status === AccountStatus::Approved;
    }

    /**
     * Compte bloqué par la modération (statut suspended ; blocked_until = fin éventuelle).
     */
    public function isBlocked(): bool
    {
        return $this->account_status === AccountStatus::Suspended;
    }

    /**
     * Signalé en interne par un administrateur (jamais visible de l'utilisateur).
     */
    public function isFlagged(): bool
    {
        return $this->flagged_at !== null;
    }

    /**
     * Comptes (client, livreur, entreprise) en attente de validation par un administrateur.
     */
    public function scopeAwaitingValidation(Builder $query): Builder
    {
        return $query
            ->whereIn('role', [Role::Client, Role::Delivery, Role::Business])
            ->where('account_status', AccountStatus::Pending);
    }

    /**
     * Route où envoyer l'utilisateur après connexion : l'espace de son rôle,
     * ou la page d'état de son compte tant qu'il n'est pas validé.
     */
    public function homeRoute(): string
    {
        if (! $this->isApproved()) {
            return $this->accountStatusRoute();
        }

        return match ($this->role) {
            Role::Admin => 'admin.dashboard',
            Role::Business => 'business.dashboard',
            Role::Delivery => 'delivery.dashboard',
            default => 'home',
        };
    }

    /**
     * Page expliquant l'état d'un compte non validé (en attente, refusé, suspendu).
     */
    public function accountStatusRoute(): string
    {
        return match ($this->account_status) {
            AccountStatus::Rejected => 'account.rejected',
            AccountStatus::Suspended => 'account.suspended',
            default => 'account.pending',
        };
    }

    /**
     * Initiales pour l'avatar : "Marie Ndong" → "MN".
     */
    public function initials(): string
    {
        $initials = collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    /**
     * Administrateur ayant validé le compte.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Commerce géré par l'utilisateur (rôle entreprise).
     */
    public function store(): HasOne
    {
        return $this->hasOne(Store::class, 'owner_id');
    }

    public function deliveryProfile(): HasOne
    {
        return $this->hasOne(DeliveryProfile::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Actions de modération visant ce compte (de la plus récente à la plus ancienne).
     */
    public function moderationActions(): HasMany
    {
        return $this->hasMany(ModerationAction::class)->latest('created_at')->latest('id');
    }

    /**
     * Historique du dossier d'inscription (du plus récent au plus ancien).
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(AccountDecision::class)->latest('created_at')->latest('id');
    }

    /**
     * Documents vérifiés par l'utilisateur (rôle admin).
     */
    public function reviewedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'reviewed_by');
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
