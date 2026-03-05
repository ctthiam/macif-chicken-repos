<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Centre de notifications
 *
 * Fichier : app/Http/Controllers/Shared/NotificationController.php
 *
 * Routes :
 *   NTF-09 : GET /api/notifications              — Liste paginée
 *   NTF-09 : PUT /api/notifications/{id}/lu       — Marquer une notif lue
 *   NTF-09 : PUT /api/notifications/tout-lire     — Tout marquer lu
 */
class NotificationController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // GET /api/notifications
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('non_lues')) {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate(15);
        $nonLues       = Notification::where('user_id', $user->id)->where('is_read', false)->count();

        return response()->json([
            'success' => true,
            'message' => 'Notifications récupérées.',
            'data'    => $notifications->items(),
            'meta'    => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'total'        => $notifications->total(),
                'non_lues'     => $nonLues,
            ],
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // PUT /api/notifications/{id}/lu
    // ══════════════════════════════════════════════════════════════

    public function marquerLu(Request $request, int $id): JsonResponse
    {
        $notif = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$notif) {
            return response()->json(['success' => false, 'message' => 'Notification introuvable.'], 404);
        }

        $notif->update(['is_read' => true]);

        return response()->json(['success' => true, 'message' => 'Notification marquée comme lue.'], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // PUT /api/notifications/tout-lire
    // ══════════════════════════════════════════════════════════════

    public function toutLire(Request $request): JsonResponse
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} notification(s) marquée(s) comme lue(s).",
            'updated' => $updated,
        ], 200);
    }
}