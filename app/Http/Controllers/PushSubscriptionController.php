<?php

namespace App\Http\Controllers;

use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abonnement de l'appareil courant aux notifications push (tous les rôles connectés).
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request, PushService $push): JsonResponse
    {
        abort_unless(PushService::isConfigured(), 404);

        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        $push->subscribe(
            $request->user(),
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? null,
            $request->userAgent(),
        );

        return response()->json(['subscribed' => true], 201);
    }

    public function destroy(Request $request, PushService $push): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);

        $push->unsubscribe($request->user(), $data['endpoint']);

        return response()->json(['subscribed' => false]);
    }
}
