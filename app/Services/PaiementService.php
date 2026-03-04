<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service : Intégration PayTech (Wave + Orange Money + Free Money)
 *
 * Fichier : app/Services/PaiementService.php
 *
 * Flow :
 *   1. initierPaiement()  → Crée transaction PayTech → retourne URL de paiement
 *   2. verifierWebhook()  → Valide signature HMAC SHA256 du webhook PayTech
 *   3. confirmerPaiement() → Met à jour commande + paiement après webhook confirmé
 *
 * Doc PayTech : https://paytech.sn/
 * Variables .env requises :
 *   PAYTECH_API_KEY=xxx
 *   PAYTECH_API_SECRET=xxx
 *   PAYTECH_ENV=test|prod  (test = sandbox)
 */
class PaiementService
{
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey    = config('services.paytech.api_key', '');
        $this->apiSecret = config('services.paytech.api_secret', '');
        $this->baseUrl   = config('services.paytech.env', 'test') === 'prod'
            ? 'https://paytech.sn'
            : 'https://paytech.sn'; // même URL, sandbox via credentials
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-01 — Initier un paiement PayTech
    // ══════════════════════════════════════════════════════════════

    /**
     * Crée une transaction PayTech et retourne l'URL de paiement.
     *
     * Enregistre un Paiement en DB avec statut 'initie'.
     * L'acheteur est redirigé vers l'URL PayTech pour payer.
     *
     * @param  Commande $commande
     * @return array{success: bool, payment_url: string|null, reference: string, message: string}
     */
    public function initierPaiement(Commande $commande): array
    {
        $reference = 'MACIF-' . $commande->id . '-' . strtoupper(Str::random(8));

        // ── Créer l'enregistrement Paiement en DB ───────────────────
        $paiement = Paiement::create([
            'commande_id'           => $commande->id,
            'user_id'               => $commande->acheteur_id,
            'montant'               => $commande->montant_total,
            'methode'               => $commande->mode_paiement,
            'reference_transaction' => $reference,
            'statut'                => 'initie',
        ]);

        // ── Appel API PayTech ────────────────────────────────────────
        try {
            $response = Http::withHeaders([
                'API_KEY'    => $this->apiKey,
                'API_SECRET' => $this->apiSecret,
            ])->post("{$this->baseUrl}/api/payment/request-payment", [
                'item_name'        => "Commande MACIF #{$commande->id}",
                'item_price'       => $commande->montant_total,
                'currency'         => 'XOF',
                'ref_command'      => $reference,
                'command_name'     => "Commande #{$commande->id} — {$commande->quantite} poulets",
                'env'              => config('services.paytech.env', 'test'),
                'ipn_url'          => config('app.url') . '/api/paiements/webhook',
                'success_url'      => config('app.url') . '/paiement/succes',
                'cancel_url'       => config('app.url') . '/paiement/annule',
            ]);

            if ($response->successful() && isset($response['token'])) {
                $paymentUrl = "https://paytech.sn/payment/checkout/{$response['token']}";

                Log::info('[PAY-01] Paiement initié', [
                    'commande_id' => $commande->id,
                    'reference'   => $reference,
                    'token'       => $response['token'],
                ]);

                return [
                    'success'     => true,
                    'payment_url' => $paymentUrl,
                    'reference'   => $reference,
                    'message'     => 'Paiement initié avec succès.',
                ];
            }

            Log::error('[PAY-01] Réponse PayTech invalide', [
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return [
                'success'     => false,
                'payment_url' => null,
                'reference'   => $reference,
                'message'     => 'Erreur lors de l\'initialisation du paiement PayTech.',
            ];

        } catch (\Exception $e) {
            Log::error('[PAY-01] Exception PayTech', ['error' => $e->getMessage()]);

            return [
                'success'     => false,
                'payment_url' => null,
                'reference'   => $reference,
                'message'     => 'Service de paiement indisponible. Réessayez plus tard.',
            ];
        }
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-02 — Vérifier signature HMAC du webhook PayTech
    // ══════════════════════════════════════════════════════════════

    /**
     * Valide l'authenticité d'un webhook PayTech via signature HMAC SHA256.
     *
     * PayTech envoie dans le header : X-PayTech-Signature
     * Calculé côté PayTech : HMAC-SHA256(payload_brut, API_SECRET)
     *
     * @param  string $payload   Corps brut de la requête (file_get_contents('php://input'))
     * @param  string $signature Header X-PayTech-Signature reçu
     * @return bool
     */
    public function verifierSignatureHmac(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha256', $payload, $this->apiSecret);
        return hash_equals($expected, $signature);
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-03 — Confirmer paiement après webhook validé
    // ══════════════════════════════════════════════════════════════

    /**
     * Met à jour la commande et le paiement après confirmation webhook.
     *
     * PAY-03 : statut_paiement passe à 'paye' — fonds bloqués en escrow.
     *
     * @param  string $reference    ref_command envoyée par PayTech
     * @param  array  $webhookData  Données complètes du webhook (pour audit)
     * @return bool
     */
    public function confirmerPaiement(string $reference, array $webhookData): bool
    {
        $paiement = Paiement::where('reference_transaction', $reference)->first();

        if (!$paiement) {
            Log::warning('[PAY-03] Paiement introuvable pour référence', ['reference' => $reference]);
            return false;
        }

        if ($paiement->statut === 'confirme') {
            Log::info('[PAY-03] Paiement déjà confirmé — doublon webhook ignoré', ['reference' => $reference]);
            return true; // idempotent
        }

        // ── Mettre à jour le paiement ────────────────────────────────
        $paiement->update([
            'statut'       => 'confirme',
            'webhook_data' => $webhookData,
        ]);

        // ── Mettre à jour la commande → fonds en escrow ──────────────
        $paiement->commande->update([
            'statut_paiement' => 'paye',
        ]);

        Log::info('[PAY-03] Paiement confirmé — fonds en escrow', [
            'commande_id' => $paiement->commande_id,
            'reference'   => $reference,
            'montant'     => $paiement->montant,
        ]);

        return true;
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-05 — Remboursement via PayTech
    // ══════════════════════════════════════════════════════════════

    /**
     * Déclenche un remboursement PayTech pour une commande annulée.
     *
     * @param  Commande $commande
     * @return bool
     */
    public function rembourserPaiement(Commande $commande): bool
    {
        $paiement = Paiement::where('commande_id', $commande->id)
            ->where('statut', 'confirme')
            ->first();

        if (!$paiement) {
            Log::warning('[PAY-05] Aucun paiement confirmé trouvé pour remboursement', [
                'commande_id' => $commande->id,
            ]);
            return false;
        }

        try {
            $response = Http::withHeaders([
                'API_KEY'    => $this->apiKey,
                'API_SECRET' => $this->apiSecret,
            ])->post("{$this->baseUrl}/api/payment/refund", [
                'ref_command' => $paiement->reference_transaction,
                'montant'     => $paiement->montant,
            ]);

            if ($response->successful()) {
                $paiement->update(['statut' => 'rembourse']);

                Log::info('[PAY-05] Remboursement effectué', [
                    'commande_id' => $commande->id,
                    'reference'   => $paiement->reference_transaction,
                ]);

                return true;
            }

            Log::error('[PAY-05] Échec remboursement PayTech', ['body' => $response->body()]);
            return false;

        } catch (\Exception $e) {
            Log::error('[PAY-05] Exception remboursement', ['error' => $e->getMessage()]);
            return false;
        }
    }
}