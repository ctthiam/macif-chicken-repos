<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommandeResource;
use App\Models\Commande;
use App\Services\PaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Paiements PayTech
 *
 * Fichier : app/Http/Controllers/Shared/PaiementController.php
 *
 * Routes :
 *   PAY-01 : POST /api/paiements/initier    — auth:sanctum, acheteur
 *   PAY-02 : POST /api/paiements/webhook    — public, vérif HMAC
 */
class PaiementController extends Controller
{
    public function __construct(
        private readonly PaiementService $paiementService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // PAY-01 — Initier un paiement
    // POST /api/paiements/initier
    // ══════════════════════════════════════════════════════════════

    /**
     * Initie un paiement PayTech pour une commande existante.
     *
     * Règles :
     *   - La commande doit appartenir à l'acheteur connecté
     *   - statut_commande doit être 'confirmee'
     *   - statut_paiement doit être 'en_attente' (pas déjà payé)
     *
     * @param  Request $request
     * @return JsonResponse  200 | 403 | 404 | 422
     */
    public function initier(Request $request): JsonResponse
    {
        $request->validate([
            'commande_id' => ['required', 'integer', 'exists:commandes,id'],
        ]);

        $commande = Commande::find($request->commande_id);

        // ── Propriété ────────────────────────────────────────────────
        if ($commande->acheteur_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à cette commande.',
            ], 403);
        }

        // ── Statut commande ──────────────────────────────────────────
        if ($commande->statut_commande !== 'confirmee') {
            return response()->json([
                'success' => false,
                'message' => "Impossible d'initier un paiement : commande « {$commande->statut_commande} ».",
            ], 422);
        }

        // ── Paiement pas déjà effectué ───────────────────────────────
        if ($commande->statut_paiement !== 'en_attente') {
            return response()->json([
                'success' => false,
                'message' => "Le paiement est déjà au statut « {$commande->statut_paiement} ».",
            ], 422);
        }

        // ── Initier via PayTech ──────────────────────────────────────
        $result = $this->paiementService->initierPaiement($commande);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 502);
        }

        return response()->json([
            'success'     => true,
            'message'     => $result['message'],
            'payment_url' => $result['payment_url'],
            'reference'   => $result['reference'],
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-02 — Webhook PayTech
    // POST /api/paiements/webhook  (route publique — pas d'auth)
    // ══════════════════════════════════════════════════════════════

    /**
     * Reçoit et traite les notifications PayTech (IPN).
     *
     * Sécurité :
     *   1. Vérifie signature HMAC-SHA256 via header X-PayTech-Signature
     *   2. Vérifie que le type_event = 'sale_complete'
     *   3. Appelle PaiementService::confirmerPaiement()
     *
     * Toujours retourner HTTP 200 à PayTech même en cas d'erreur
     * (sinon PayTech retry en boucle).
     *
     * @param  Request $request
     * @return JsonResponse  200
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-PayTech-Signature', '');

        // ── PAY-02 : Vérifier signature HMAC ────────────────────────
        if (!$this->paiementService->verifierSignatureHmac($payload, $signature)) {
            \Illuminate\Support\Facades\Log::warning('[PAY-02] Signature HMAC invalide', [
                'ip'        => $request->ip(),
                'signature' => $signature,
            ]);
            // On retourne 200 pour éviter les retries PayTech
            return response()->json(['success' => false, 'message' => 'Signature invalide.'], 200);
        }

        $data = $request->all();

        // ── Traiter uniquement les paiements complétés ───────────────
        if (($data['type_event'] ?? '') !== 'sale_complete') {
            return response()->json(['success' => true, 'message' => 'Événement ignoré.'], 200);
        }

        $reference = $data['ref_command'] ?? null;

        if (!$reference) {
            return response()->json(['success' => false, 'message' => 'ref_command manquante.'], 200);
        }

        // ── PAY-03 : Confirmer paiement → escrow ────────────────────
        $this->paiementService->confirmerPaiement($reference, $data);

        return response()->json(['success' => true, 'message' => 'Webhook traité.'], 200);
    }
}