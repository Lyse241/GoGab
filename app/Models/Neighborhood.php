<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Neighborhood extends Model
{
    /**
     * La table neighborhoods n'a pas de colonnes created_at / updated_at.
     */
    public $timestamps = false;

    /**
     * Zones de Libreville. Deux quartiers de la même zone sont « proches » (pas de GPS).
     */
    public const ZONES = ['Nord', 'Centre', 'Est', 'Sud'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'zone',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Livreurs rattachés à ce quartier.
     */
    public function deliveryProfiles(): HasMany
    {
        return $this->hasMany(DeliveryProfile::class, 'base_neighborhood_id');
    }
}
