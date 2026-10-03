<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Pages d'erreur Gogab (Inertia, page « Error ») avec un bouton de retour adapté au rôle.
 */
class ErrorPage
{
    /** Statuts affichés avec la page Gogab (500 et 503 seulement hors debug). */
    public const STATUSES = [403, 404, 419, 500, 503];

    public static function render(Request $request, int $status): Response
    {
        return Inertia::render('Error', [
            'status' => $status,
            'home' => self::home(self::user($request)),
        ])->toResponse($request)->setStatusCode($status);
    }

    /**
     * Espace où revenir : celui du rôle (ou la page d'état d'un compte non validé), sinon l'accueil.
     *
     * @return array{url: string, label: string}
     */
    public static function home(?User $user): array
    {
        if (! $user || $user->isDeleted()) {
            return ['url' => route('home'), 'label' => 'Retour à l’accueil'];
        }

        if (! $user->isApproved()) {
            return ['url' => route($user->accountStatusRoute()), 'label' => 'Suivre mon inscription'];
        }

        return [
            'url' => route($user->homeRoute()),
            'label' => match ($user->role) {
                Role::Admin => 'Retour à l’administration',
                Role::Business => 'Retour à mon commerce',
                Role::Delivery => 'Retour à mes courses',
                default => 'Retour au catalogue',
            },
        ];
    }

    /**
     * Compte connecté, si la session est disponible (une erreur peut survenir avant).
     */
    private static function user(Request $request): ?User
    {
        try {
            return $request->hasSession() ? $request->user() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
