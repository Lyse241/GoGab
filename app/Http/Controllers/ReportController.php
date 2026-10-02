<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;

/**
 * « Signaler un problème » depuis une commande (client, entreprise, livreur).
 * Aucun retour n'est fait à la personne signalée.
 */
class ReportController extends Controller
{
    public function store(StoreReportRequest $request, Order $order, ReportService $reports): RedirectResponse
    {
        $reports->create(
            $request->user(),
            $order,
            User::findOrFail($request->validated('reported_user_id')),
            $request->reason(),
            $request->validated('description'),
        );

        return back()->with('success', 'Merci, votre signalement a été envoyé à l’équipe Gogab. Vous serez prévenu une fois qu’il aura été traité.');
    }
}
