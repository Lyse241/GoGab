<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notifications in-app de l'utilisateur connecté (cloche du header + page /notifications).
 * Pas de WebSocket : la cloche interroge /notifications/unread toutes les 15 secondes.
 */
class NotificationController extends Controller
{
    private const FILTERS = ['all', 'unread', 'read'];

    /**
     * Page liste, filtrable : ?filter=all|unread|read.
     */
    public function index(Request $request): Response
    {
        $filter = $request->validate([
            'filter' => ['nullable', Rule::in(self::FILTERS)],
        ])['filter'] ?? 'all';

        $user = $request->user();

        $notifications = $user->notifications()
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($filter === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification) => $this->present($notification));

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'counts' => [
                'all' => $user->notifications()->count(),
                'unread' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * JSON pour la cloche : nombre de non lues + 10 dernières notifications.
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()
                ->limit(10)
                ->get()
                ->map(fn (DatabaseNotification $notification) => $this->present($notification)),
        ]);
    }

    public function read(Request $request, string $id): JsonResponse|RedirectResponse
    {
        // Seules les notifications de l'utilisateur connecté sont accessibles (404 sinon).
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return $this->respond($request);
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->respond($request, 'Toutes vos notifications sont marquées comme lues.');
    }

    /**
     * JSON pour les appels de la cloche, redirection pour un formulaire classique.
     */
    private function respond(Request $request, ?string $success = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
        }

        return $success ? back()->with('success', $success) : back();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? '',
            'message' => $notification->data['message'] ?? '',
            'url' => $notification->data['url'] ?? null,
            'type' => $notification->data['type'] ?? 'info',
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}
