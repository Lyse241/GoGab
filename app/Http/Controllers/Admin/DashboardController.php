<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrderSupervision;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord admin : cartes du jour, commandes des 7 derniers jours, répartition par
 * statut, derniers événements. La liste des commandes est sur /admin/orders.
 */
class DashboardController extends Controller
{
    public function __invoke(OrderSupervision $supervision): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => $supervision->dashboard(),
            'week' => $supervision->lastSevenDays(),
            'byStatus' => $supervision->byStatus(),
            'events' => $supervision->latestEvents(),
            'stuckMinutes' => $supervision->stuckMinutes(),
        ]);
    }
}
