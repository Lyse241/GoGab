<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Catalogue de l'espace entreprise : une entreprise validée ne voit et ne modifie que les
 * produits de son propre commerce. L'admin passe par ses propres écrans (Admin\ProductController).
 */
class ProductPolicy
{
    public function create(User $user): bool
    {
        return $user->isBusiness() && $user->isApproved() && $user->store !== null;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isBusiness()
            && $user->isApproved()
            && $user->store !== null
            && $product->store_id === $user->store->id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
