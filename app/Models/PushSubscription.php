<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abonnement Web Push d'un navigateur (un utilisateur peut en avoir plusieurs : téléphone, ordinateur).
 */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint_hash', 'endpoint', 'p256dh_key', 'auth_key', 'content_encoding', 'user_agent'];

    protected $hidden = ['p256dh_key', 'auth_key'];

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
