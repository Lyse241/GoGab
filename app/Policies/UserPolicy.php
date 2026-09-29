<?php

namespace App\Policies;

use App\Enums\AccountStatus;
use App\Models\User;

/**
 * Validation des comptes (espace admin) et correction d'un dossier refusé (côté utilisateur).
 */
class UserPolicy
{
    /**
     * Un administrateur validé peut examiner et décider sur un compte client, livreur ou entreprise
     * (jamais sur un autre administrateur, ni sur son propre compte).
     */
    public function review(User $admin, User $account): bool
    {
        return $admin->isAdmin()
            && $admin->isApproved()
            && ! $account->isAdmin()
            && ! $admin->is($account);
    }

    /**
     * Modération (avertir, bloquer, débloquer, signaler) : même règle que l'examen d'un compte —
     * un administrateur validé, jamais sur un autre administrateur ni sur lui-même.
     */
    public function moderate(User $admin, User $account): bool
    {
        return $this->review($admin, $account);
    }

    /**
     * Seul l'utilisateur lui-même corrige et renvoie son dossier, et seulement s'il a été refusé.
     */
    public function resubmit(User $user, User $account): bool
    {
        return $user->is($account) && $account->account_status === AccountStatus::Rejected;
    }
}
