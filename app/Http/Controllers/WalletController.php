<?php

namespace App\Http\Controllers;

use App\Models\WalletEntry;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Mon solde » de l'entreprise (/business/wallet) et du livreur (/delivery/wallet) : ce que Gogab
 * leur doit (ou ce que le livreur doit remettre) et le détail des mouvements du grand livre.
 */
class WalletController extends Controller
{
    public function __invoke(Request $request, WalletService $wallet): Response
    {
        $user = $request->user();

        return Inertia::render('Wallet/Index', [
            'balance' => $wallet->balance($user),
            'entries' => WalletEntry::where('user_id', $user->id)
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (WalletEntry $entry) => [
                    'id' => $entry->id,
                    'type' => $entry->type->label(),
                    'description' => $entry->description,
                    'amount' => $entry->amount,
                    'created_at' => $entry->created_at?->timezone(config('gogab.timezone'))->format('d/m/Y H:i'),
                ]),
            'commission_rate' => (float) config('gogab.commission_rate'),
        ]);
    }
}
