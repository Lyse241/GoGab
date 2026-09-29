<?php

namespace App\Models;

use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryProfile extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_brand',
        'plate_number',
        'license_number',
        'base_neighborhood_id',
        'is_available',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'is_available' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quartier de rattachement du livreur (sert à la notion de proximité).
     */
    public function baseNeighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class, 'base_neighborhood_id');
    }
}
