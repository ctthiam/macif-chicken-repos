<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommandeResource;
use App\Models\Commande;
use App\Services\PaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur : Paiements PayTech
 *
 * Fichier : app/Http/Controllers/Shared/PaiementController.php
 *
 * Routes :
 *   PAY-01 : POST /api/paiements/initier              — auth:sanctum
 *   PAY-02 : POST /api/paiements/webhook              — public (commandes)
 *   ABO-02 : POST /api/paiements/webhook-abonnement   — public (abonnements)
 */
class PaiementController extends Controller
{
    public function __construct(
        private readonly PaiementService $paiementService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // PAY-01 — Initier paiement commande
    // ══════════════════════════════════════════════════════════════

    public function initier(Request $request): JsonResponse
    {
        $request->validate([
            'commande_id' => ['required', 'integer', 'exists:commandes,id'],
        ]);

        $commande = Commande::find($request->commande_id);

        if ($commande->acheteur_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
        }

        if ($commande->statut_commande !== 'confirmee') {
            return response()->json(['success' => false, 'message' => "Commande « {$commande->statut_commande} » non payable."], 422);
        }

        if ($commande->statut_paiement !== 'en_attente') {
            return response()->json(['success' => false, 'message' => "Paiement déjà « {$commande->statut_paiement} »."], 422);
        }

        $result = $this->paiementService->initierPaiement($commande);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 502);
        }

        return response()->json([
            'success'     => true,
            'message'     => $result['message'],
            'payment_url' => $result['payment_url'],
            'reference'   => $result['reference'],
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-02 — Webhook PayTech commandes
    // POST /api/paiements/webhook
    // ══════════════════════════════════════════════════════════════

    public function webhook(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-PayTech-Signature', '');

        if (!$this->paiementService->verifierSignatureHmac($payload, $signature)) {
            Log::warning('[PAY-02] Signature HMAC invalide', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Signature invalide.'], 200);
        }

        $data = $request->all();

        if (($data['type_event'] ?? '') !== 'sale_complete') {
            return response()->json(['success' => true, 'message' => 'Événement ignoré.'], 200);
        }

        $reference = $data['ref_command'] ?? null;
        if (!$reference) {
            return response()->json(['success' => false, 'message' => 'ref_command manquante.'], 200);
        }

        $this->paiementService->confirmerPaiement($reference, $data);

        return response()->json(['success' => true, 'message' => 'Webhook traité.'], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-02 — Webhook PayTech abonnements
    // POST /api/paiements/webhook-abonnement
    // ══════════════════════════════════════════════════════════════

    /**
     * Reçoit la confirmation de paiement d'un abonnement.
     * Active l'abonnement en DB.
     *
     * @param  Request $request
     * @return JsonResponse  200 (toujours — évite les retries PayTech)
     */
    public function webhookAbonnement(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-PayTech-Signature', '');

        if (!$this->paiementService->verifierSignatureHmac($payload, $signature)) {
            Log::warning('[ABO-02] Signature HMAC invalide webhook abonnement', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Signature invalide.'], 200);
        }

        $data = $request->all();

        if (($data['type_event'] ?? '') !== 'sale_complete') {
            return response()->json(['success' => true, 'message' => 'Événement ignoré.'], 200);
        }

        $reference = $data['ref_command'] ?? null;
        if (!$reference) {
            return response()->json(['success' => false, 'message' => 'ref_command manquante.'], 200);
        }

        $this->paiementService->confirmerPaiementAbonnement($reference, $data);

        return response()->json(['success' => true, 'message' => 'Abonnement activé.'], 200);
    }
}