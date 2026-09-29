<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    /**
     * Gérer un commerce depuis l'espace entreprise (profil, ouverture) : son propriétaire,
     * avec un compte entreprise validé. L'admin passe par ses propres écrans.
     */
    public function manage(User $user, Store $store): bool
    {
        return $user->isBusiness()
            && $user->isApproved()
            && $store->owner_id === $user->id;
    }
}
