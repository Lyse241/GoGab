<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

/**
 * Signalements : réservés aux admins validés. Ni le signalant ni la personne signalée n'y ont
 * accès (le signalant reçoit seulement la notification « traité »).
 */
class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isApproved();
    }

    public function view(User $user, Report $report): bool
    {
        return $this->viewAny($user);
    }

    public function handle(User $user, Report $report): bool
    {
        return $this->viewAny($user) && $report->status->isPending();
    }
}
