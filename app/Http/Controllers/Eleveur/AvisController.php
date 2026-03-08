<?php

namespace App\Http\Controllers\Eleveur;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Avis éleveur
 *
 * Fichier : app/Http/Controllers/Eleveur/AvisController.php
 *
 * Routes :
 *   GET /api/eleveur/avis             — Liste des avis reçus (paginé)
 *   PUT /api/eleveur/avis/{id}/reply  — AVI-03 : Répondre à un avis
 */
class AvisController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    // ══════════════════════════════════════════════════════════════
    // GET /api/eleveur/avis — Liste des avis reçus
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $eleveur = $request->user();

        $avis = Avis::where('cible_id', $eleveur->id)
            ->with('auteur:id,name,avatar', 'commande:id,created_at')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Avis récupérés avec succès.',
            'data'    => $avis->items(),
            'meta'    => [
                'current_page' => $avis->currentPage(),
                'last_page'    => $avis->lastPage(),
                'total'        => $avis->total(),
            ],
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // AVI-03 — Répondre à un avis
    // PUT /api/eleveur/avis/{id}/reply
    // ══════════════════════════════════════════════════════════════

    /**
     * L'éleveur peut répondre à un avis le concernant.
     * Une seule réponse par avis (écrase si déjà répondu).
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse  200 | 403 | 404
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reply' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $eleveur = $request->user();
        $avis    = Avis::find($id);

        if (!$avis) {
            return response()->json([
                'success' => false,
                'message' => 'Avis introuvable.',
            ], 404);
        }

        // Seul l'éleveur ciblé peut répondre
        if ($avis->cible_id !== $eleveur->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez répondre qu\'aux avis qui vous concernent.',
            ], 403);
        }

        $avis->update(['reply' => $request->reply]);

        // Notifier l'auteur de l'avis (l'acheteur)
        $this->notificationService->notifier(
            userId:  $avis->auteur_id,
            titre:   '💬 Réponse à votre avis',
            message: $eleveur->name . ' a répondu à votre avis : "' . mb_substr($request->reply, 0, 80) . (mb_strlen($request->reply) > 80 ? '…' : '') . '"',
            type:    'review',
            data:    ['avis_id' => $avis->id, 'eleveur_id' => $eleveur->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Réponse publiée avec succès.',
            'data'    => [
                'id'    => $avis->id,
                'reply' => $avis->reply,
            ],
        ], 200);
    }
}