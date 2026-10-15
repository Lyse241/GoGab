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
     * Zones d'activité = les trois communes du Grand Libreville. Un livreur choisit une commune et
     * voit les courses de TOUS les quartiers de cette commune (pas de GPS : la proximité, c'est la commune).
     */
    public const ZONES = ['Libreville', 'Akanda', 'Owendo'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'zone',
        'latitude',
        'longitude',
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
