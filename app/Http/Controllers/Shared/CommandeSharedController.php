<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommandeResource;
use App\Models\Commande;
use App\Services\EscrowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Actions partagées sur les commandes
 *
 * Fichier : app/Http/Controllers/Shared/CommandeSharedController.php
 *
 * Routes couvertes (prefix /api/commandes) :
 *   CMD-06 : POST /api/commandes/{id}/confirmer-livraison  — Acheteur confirme réception
 *   CMD-08 : POST /api/commandes/{id}/litige               — Ouvrir litige (Sprint CMD-08)
 */
class CommandeSharedController extends Controller
{
    public function __construct(
        private readonly EscrowService $escrowService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // CMD-06 — Confirmer réception livraison
    // POST /api/commandes/{id}/confirmer-livraison
    // ══════════════════════════════════════════════════════════════

    /**
     * L'acheteur confirme avoir reçu sa commande.
     * Libère immédiatement l'escrow vers l'éleveur.
     *
     * Règles métier :
     *   - Seul l'acheteur de la commande peut confirmer
     *   - statut_commande doit être 'livree'
     *   - statut_paiement doit être 'paye' (pas déjà libéré)
     *   - Appel EscrowService::liberer() → statut_paiement = 'libere' + escrow_libere_at
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse  200 | 401 | 403 | 404 | 422
     */
    public function confirmerLivraison(Request $request, int $id): JsonResponse
    {
        $user     = $request->user();
        $commande = Commande::with(['stock', 'eleveur', 'acheteur'])->find($id);

        // ── 1. Existence ─────────────────────────────────────────────
        if (!$commande) {
            return response()->json([
                'success' => false,
                'message' => 'Commande introuvable.',
                'errors'  => [],
            ], 404);
        }

        // ── 2. Seul l'acheteur de la commande peut confirmer ─────────
        if ($commande->acheteur_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à confirmer cette livraison.',
                'errors'  => [],
            ], 403);
        }

        // ── 3. Statut commande doit être 'livree' ─────────────────────
        if ($commande->statut_commande !== 'livree') {
            return response()->json([
                'success' => false,
                'message' => "La commande est actuellement « {$commande->statut_commande} ». La confirmation est uniquement possible après livraison.",
                'errors'  => [],
            ], 422);
        }

        // ── 4. Escrow pas déjà libéré ─────────────────────────────────
        if ($commande->statut_paiement === 'libere') {
            return response()->json([
                'success' => false,
                'message' => 'Les fonds ont déjà été libérés pour cette commande.',
                'errors'  => [],
            ], 422);
        }

        // ── 5. Paiement doit être 'paye' ──────────────────────────────
        if ($commande->statut_paiement !== 'paye') {
            return response()->json([
                'success' => false,
                'message' => "Impossible de libérer les fonds : statut paiement = « {$commande->statut_paiement} ».",
                'errors'  => [],
            ], 422);
        }

        // ── 6. Libérer l'escrow ───────────────────────────────────────
        $this->escrowService->liberer($commande);

        $commande->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Réception confirmée. Les fonds ont été libérés à l\'éleveur.',
            'data'    => new CommandeResource($commande),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-08 — Ouvrir un litige (stub — Sprint suivant)
    // POST /api/commandes/{id}/litige
    // ══════════════════════════════════════════════════════════════

    public function ouvrirLitige(Request $request, int $id): JsonResponse
    {
        // TODO CMD-08
        return response()->json(['success' => false, 'message' => 'Non implémenté — CMD-08.'], 501);
    }
}