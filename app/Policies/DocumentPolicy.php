<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Pièces justificatives (disque privé) : jamais d'accès public.
 */
class DocumentPolicy
{
    /**
     * Voir le fichier : son propriétaire, ou un administrateur validé.
     */
    public function view(User $user, Document $document): bool
    {
        return $user->id === $document->user_id
            || ($user->isAdmin() && $user->isApproved());
    }

    /**
     * Approuver ou refuser : un administrateur qui peut examiner le compte propriétaire.
     */
    public function review(User $admin, Document $document): bool
    {
        return (new UserPolicy)->review($admin, $document->user);
    }
}
