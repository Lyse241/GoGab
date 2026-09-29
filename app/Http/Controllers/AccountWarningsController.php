<?php

namespace App\Http\Controllers;

use App\Enums\ModerationType;
use App\Models\ModerationAction;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Mes avertissements » : avertissements reçus de la modération et accusé de réception.
 */
class AccountWarningsController extends Controller
{
    public function __construct(private readonly ModerationService $moderation) {}

    public function index(Request $request): Response
    {
        $warnings = $request->user()->moderationActions()
            ->where('type', ModerationType::Warning)
            ->get()
            ->map(fn (ModerationAction $warning) => [
                'id' => $warning->id,
                'reason_label' => $warning->reason?->label(),
                'message' => $warning->message,
                'at' => $this->moderation->localDate($warning->created_at),
                'acknowledged' => $warning->acknowledged_at !== null,
            ]);

        return Inertia::render('Account/Warnings', ['warnings' => $warnings]);
    }

    /**
     * « J'ai compris ».
     */
    public function acknowledge(Request $request, ModerationAction $warning): RedirectResponse|JsonResponse
    {
        $this->moderation->acknowledge($request->user(), $warning);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
