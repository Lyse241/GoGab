<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectionRequest;
use App\Models\Document;
use App\Models\User;
use App\Services\AccountValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Décisions de l'admin sur un dossier. Toute la logique est dans AccountValidationService
 * (qui revérifie la Policy) ; les routes sont aussi protégées par le middleware "can".
 */
class AccountValidationController extends Controller
{
    public function __construct(private readonly AccountValidationService $validation) {}

    public function approveDocument(Request $request, Document $document): RedirectResponse
    {
        $this->validation->approveDocument($request->user(), $document);

        return back()->with('success', "{$document->type->label()} approuvé.");
    }

    public function rejectDocument(RejectionRequest $request, Document $document): RedirectResponse
    {
        $this->validation->rejectDocument($request->user(), $document, $request->validated('reason'));

        return back()->with('success', "{$document->type->label()} refusé.");
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->validation->approveAccount($request->user(), $user);

        return back()->with('success', "Compte de {$user->name} validé : une notification de bienvenue lui a été envoyée.");
    }

    public function reject(RejectionRequest $request, User $user): RedirectResponse
    {
        $this->validation->rejectAccount($request->user(), $user, $request->validated('reason'));

        return back()->with('success', "Inscription de {$user->name} refusée : le motif lui a été envoyé.");
    }
}
