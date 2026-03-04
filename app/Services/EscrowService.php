<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service : Gestion de l'escrow (séquestre des fonds)
 *
 * Fichier : app/Services/EscrowService.php
 *
 * PAY-04 : liberer()    — Virement PayTech vers l'éleveur après livraison confirmée
 * PAY-05 : rembourser() — Remboursement PayTech vers l'acheteur en cas d'annulation
 *
 * Intégration PayTech :
 *   Virement  : POST https://paytech.sn/api/payment/transfer
 *   Refund    : POST https://paytech.sn/api/payment/refund
 *
 * Les appels PayTech réels sont mockés en test via Http::fake().
 */
class EscrowService
{
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;

    public function __construct(
        private readonly NotificationService $notificationService
    ) {
        $this->apiKey    = config('services.paytech.api_key', '');
        $this->apiSecret = config('services.paytech.api_secret', '');
        $this->baseUrl   = 'https://paytech.sn';
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-04 — Libérer les fonds escrow vers l'éleveur
    // ══════════════════════════════════════════════════════════════

    /**
     * Libère les fonds de l'escrow vers l'éleveur après livraison confirmée.
     *
     * Flow :
     *   1. Vérifie que statut_paiement = 'paye' (fonds en escrow)
     *   2. Appel PayTech transfer → virement montant_eleveur vers l'éleveur
     *   3. Met à jour statut_paiement = 'libere' + escrow_libere_at
     *   4. Notifie l'éleveur
     *
     * En cas d'échec PayTech : lève une Exception (la commande reste 'paye')
     * pour permettre un retry via le job.
     *
     * @param  Commande $commande
     * @return void
     * @throws \Exception si paiement non éligible ou échec PayTech
     */
    public function liberer(Commande $commande): void
    {
        if ($commande->statut_paiement !== 'paye') {
            throw new \Exception(
                "Impossible de libérer : statut_paiement = « {$commande->statut_paiement} »"
            );
        }

        // Trouver le paiement confirmé associé
        $paiement = Paiement::where('commande_id', $commande->id)
            ->where('statut', 'confirme')
            ->first();

        DB::transaction(function () use ($commande, $paiement) {

            // ── PAY-04 : Virement PayTech vers l'éleveur ────────────
            if ($paiement && $this->apiKey) {
                $response = Http::withHeaders([
                    'API_KEY'    => $this->apiKey,
                    'API_SECRET' => $this->apiSecret,
                ])->post("{$this->baseUrl}/api/payment/transfer", [
                    'ref_command'  => $paiement->reference_transaction,
                    'montant'      => $commande->montant_eleveur,
                    'beneficiaire' => $commande->eleveur_id,
                    'motif'        => "Paiement commande #{$commande->id} — MACIF CHICKEN",
                ]);

                if (!$response->successful()) {
                    Log::error('[PAY-04] Échec virement PayTech', [
                        'commande_id' => $commande->id,
                        'status'      => $response->status(),
                        'body'        => $response->body(),
                    ]);
                    throw new \Exception('Échec du virement PayTech vers l\'éleveur.');
                }

                Log::info('[PAY-04] Virement PayTech effectué', [
                    'commande_id'    => $commande->id,
                    'montant_eleveur'=> $commande->montant_eleveur,
                ]);
            }

            // ── Mettre à jour la commande ────────────────────────────
            $commande->update([
                'statut_paiement'  => 'libere',
                'escrow_libere_at' => now(),
            ]);

            // ── Notifier l'éleveur ────────────────────────────────────
            $this->notificationService->notifier(
                userId:  $commande->eleveur_id,
                titre:   'Fonds reçus',
                message: "Les fonds de la commande #{$commande->id} ({$commande->montant_eleveur} FCFA) ont été virés.",
                type:    'payment',
                data:    ['commande_id' => $commande->id, 'montant' => $commande->montant_eleveur]
            );
        });
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-05 — Rembourser l'acheteur
    // ══════════════════════════════════════════════════════════════

    /**
     * Rembourse l'acheteur via PayTech refund API.
     *
     * Appelé depuis :
     *   - CMD-04 (annulation acheteur avant confirmation éleveur)
     *   - Admin (résolution litige en faveur acheteur)
     *
     * Si aucun paiement confirmé n'existe (commande jamais payée),
     * met simplement statut_paiement = 'rembourse' sans appel PayTech.
     *
     * @param  Commande $commande
     * @return void
     */
    public function rembourser(Commande $commande): void
    {
        $paiement = Paiement::where('commande_id', $commande->id)
            ->where('statut', 'confirme')
            ->first();

        DB::transaction(function () use ($commande, $paiement) {

            // ── PAY-05 : Appel refund PayTech si paiement existe ─────
            if ($paiement && $this->apiKey) {
                $response = Http::withHeaders([
                    'API_KEY'    => $this->apiKey,
                    'API_SECRET' => $this->apiSecret,
                ])->post("{$this->baseUrl}/api/payment/refund", [
                    'ref_command' => $paiement->reference_transaction,
                    'montant'     => $paiement->montant,
                    'motif'       => "Remboursement commande #{$commande->id} — MACIF CHICKEN",
                ]);

                if ($response->successful()) {
                    $paiement->update(['statut' => 'rembourse']);

                    Log::info('[PAY-05] Remboursement PayTech effectué', [
                        'commande_id' => $commande->id,
                        'montant'     => $paiement->montant,
                    ]);
                } else {
                    Log::error('[PAY-05] Échec remboursement PayTech', [
                        'commande_id' => $commande->id,
                        'body'        => $response->body(),
                    ]);
                    // On ne bloque pas la commande — le remboursement manuel peut suivre
                }
            }

            // ── Mettre à jour la commande ────────────────────────────
            $commande->update(['statut_paiement' => 'rembourse']);

            // ── Notifier l'acheteur ───────────────────────────────────
            $this->notificationService->notifier(
                userId:  $commande->acheteur_id,
                titre:   'Remboursement effectué',
                message: "Votre commande #{$commande->id} a été remboursée ({$commande->montant_total} FCFA).",
                type:    'payment',
                data:    ['commande_id' => $commande->id, 'montant' => $commande->montant_total]
            );
        });
    }
}