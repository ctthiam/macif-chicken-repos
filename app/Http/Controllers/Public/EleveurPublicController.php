<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EleveurPublicResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Profil public éleveur
 *
 * Fichier : app/Http/Controllers/Public/EleveurPublicController.php
 *
 * Routes couvertes :
 *   PRO-06 : GET /api/eleveurs/{id}/public
 *
 * Accessible sans authentification — route publique.
 */
class EleveurPublicController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // PRO-06 — Page publique d'un éleveur
    // GET /api/eleveurs/{id}/public
    // ══════════════════════════════════════════════════════════════

    /**
     * Retourne le profil public d'un éleveur avec :
     *   - Ses infos publiques (nom, ville, avatar, certified)
     *   - Son profil poulailler (description, localisation, photos)
     *   - Ses stocks actuellement disponibles (statut = 'disponible')
     *   - Ses 10 derniers avis non signalés
     *
     * Incrémente le compteur de vues sur chaque stock affiché.
     * Ne nécessite pas d'authentification.
     *
     * @param  int $id  ID de l'éleveur
     * @return JsonResponse  200 | 404
     */
    public function show(int $id): JsonResponse
    {
        // ── 1. Trouver l'éleveur actif ──────────────────────────────
        $eleveur = User::where('id', $id)
            ->where('role', 'eleveur')
            ->where('is_active', true)
            ->first();

        if (!$eleveur) {
            return response()->json([
                'success' => false,
                'message' => 'Éleveur introuvable.',
                'errors'  => [],
            ], 404);
        }

        // ── 2. Charger le profil poulailler ─────────────────────────
        $eleveur->load('eleveurProfile');

        // ── 3. Charger les stocks disponibles ───────────────────────
        $eleveur->load(['stocks' => function ($query) {
            $query->where('statut', 'disponible')
                ->where(function ($q) {
                    $q->whereNull('date_disponibilite')
                        ->orWhere('date_disponibilite', '<=', now()->toDateString());
                })
                ->orderByDesc('created_at');
        }]);

        // ── 4. Charger les 10 derniers avis non signalés ────────────
        $eleveur->load(['avisRecus' => function ($query) {
            $query->where('is_reported', false)
                ->with('auteur:id,name,avatar')
                ->orderByDesc('created_at')
                ->limit(10);
        }]);

        // ── 5. Incrémenter les vues sur les stocks chargés ──────────
        // On fait un update groupé pour éviter N requêtes
        if ($eleveur->stocks->isNotEmpty()) {
            $eleveur->stocks()
                ->where('statut', 'disponible')
                ->increment('vues');
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil éleveur récupéré avec succès.',
            'data'    => new EleveurPublicResource($eleveur),
        ], 200);
    }
}