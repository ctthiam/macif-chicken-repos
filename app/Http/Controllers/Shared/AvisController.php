<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Commande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Avis — actions partagées acheteur/cible
 *
 * Fichier : app/Http/Controllers/Shared/AvisController.php
 *
 * Routes :
 *   AVI-01/02 : POST /api/avis                    — auth:sanctum
 *   AVI-06    : POST /api/avis/{id}/signaler       — auth:sanctum
 */
class AvisController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // AVI-01 + AVI-02 — Laisser un avis (note 1-5 + commentaire)
    // POST /api/avis
    // ══════════════════════════════════════════════════════════════

    /**
     * Crée un avis après vérification :
     *   - Commande appartient à l'acheteur connecté
     *   - statut_commande = 'livree'
     *   - Pas déjà d'avis pour cette commande (unique commande_id)
     *
     * @param  Request $request
     * @return JsonResponse  201 | 403 | 404 | 422
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'commande_id' => ['required', 'integer', 'exists:commandes,id'],
            'note'        => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $acheteur = $request->user();
        $commande = Commande::find($request->commande_id);

        // ── Vérifier propriété ────────────────────────────────────
        if ($commande->acheteur_id !== $acheteur->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        // ── Vérifier statut livree ────────────────────────────────
        if ($commande->statut_commande !== 'livree') {
            return response()->json([
                'success' => false,
                'message' => "Impossible de laisser un avis : commande « {$commande->statut_commande} ».",
                'errors'  => ['commande_id' => ['La commande doit être livrée pour laisser un avis.']],
            ], 422);
        }

        // ── Vérifier unicité (1 avis par commande) ────────────────
        if (Avis::where('commande_id', $commande->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Un avis existe déjà pour cette commande.',
                'errors'  => ['commande_id' => ['Vous avez déjà laissé un avis pour cette commande.']],
            ], 422);
        }

        $avis = Avis::create([
            'commande_id' => $commande->id,
            'auteur_id'   => $acheteur->id,
            'cible_id'    => $commande->eleveur_id,
            'note'        => $request->note,
            'commentaire' => $request->commentaire,
        ]);

        // ── AVI-04 : Recalcul note moyenne ────────────────────────
        Avis::recalculeNoteMoyenne($commande->eleveur_id);

        return response()->json([
            'success' => true,
            'message' => 'Avis publié avec succès.',
            'data'    => [
                'id'          => $avis->id,
                'note'        => $avis->note,
                'commentaire' => $avis->commentaire,
                'created_at'  => $avis->created_at->toISOString(),
            ],
        ], 201);
    }

    // ══════════════════════════════════════════════════════════════
    // AVI-06 — Signaler un avis abusif
    // POST /api/avis/{id}/signaler
    // ══════════════════════════════════════════════════════════════

    /**
     * Marque un avis comme signalé (is_reported = true).
     * N'importe quel utilisateur connecté peut signaler.
     * L'avis signalé disparaît du profil public (filtré par is_reported).
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse  200 | 404
     */
    public function signaler(Request $request, int $id): JsonResponse
    {
        $avis = Avis::find($id);

        if (!$avis) {
            return response()->json([
                'success' => false,
                'message' => 'Avis introuvable.',
            ], 404);
        }

        if ($avis->is_reported) {
            return response()->json([
                'success' => true,
                'message' => 'Avis déjà signalé.',
            ], 200);
        }

        $avis->update(['is_reported' => true]);

        // Recalcul note moyenne (l'avis signalé est exclu du calcul)
        Avis::recalculeNoteMoyenne($avis->cible_id);

        return response()->json([
            'success' => true,
            'message' => 'Avis signalé avec succès. Notre équipe va l\'examiner.',
        ], 200);
    }
}