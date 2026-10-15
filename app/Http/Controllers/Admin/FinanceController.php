<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OrderTransitionException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletEntry;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finances : soldes des entreprises et des livreurs (grand livre WalletService) et règlements.
 */
class FinanceController extends Controller
{
    public function index(): Response
    {
        $accounts = User::query()
            ->active()
            ->whereIn('role', ['business', 'delivery'])
            ->whereHas('walletEntries')
            ->withSum('walletEntries as balance', 'amount')
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'balance' => (int) $user->balance,
            ])
            ->values();

        return Inertia::render('Admin/Finances/Index', [
            'accounts' => $accounts,
            'totals' => [
                'owed_to_users' => $accounts->where('balance', '>', 0)->sum('balance'),
                'owed_to_gogab' => -$accounts->where('balance', '<', 0)->sum('balance'),
                'commission' => -(int) WalletEntry::where('type', 'commission')->sum('amount'),
            ],
            'commission_rate' => (float) config('gogab.commission_rate'),
            'recent' => WalletEntry::with('user:id,name')
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn (WalletEntry $entry) => [
                    'id' => $entry->id,
                    'user' => $entry->user?->name,
                    'type' => $entry->type->label(),
                    'description' => $entry->description,
                    'amount' => $entry->amount,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function settle(Request $request, User $user, WalletService $wallet): RedirectResponse
    {
        abort_unless(in_array($user->role->value, ['business', 'delivery'], true), 404);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'amount.required' => 'Indiquez le montant réglé.',
            'amount.integer' => 'Le montant doit être un nombre entier de FCFA.',
            'amount.min' => 'Le montant doit être supérieur à zéro.',
        ]);

        try {
            $wallet->settle($request->user(), $user, (int) $data['amount'], $data['note'] ?? null);
        } catch (OrderTransitionException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', "Règlement enregistré pour {$user->name}.");
    }
}
