<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service : Intégration PayTech
 *
 * Fichier : app/Services/PaiementService.php
 *
 * Gère les paiements pour :
 *   - Commandes (PAY-01/02/03)
 *   - Abonnements (ABO-02)
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
        $this->baseUrl   = 'https://paytech.sn';
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-01 — Initier paiement commande
    // ══════════════════════════════════════════════════════════════

    public function initierPaiement(Commande $commande): array
    {
        $reference = 'MACIF-' . $commande->id . '-' . strtoupper(Str::random(8));

        Paiement::create([
            'commande_id'           => $commande->id,
            'user_id'               => $commande->acheteur_id,
            'montant'               => $commande->montant_total,
            'methode'               => $commande->mode_paiement,
            'reference_transaction' => $reference,
            'statut'                => 'initie',
        ]);

        try {
            $response = Http::withHeaders([
                'API_KEY'    => $this->apiKey,
                'API_SECRET' => $this->apiSecret,
            ])->post("{$this->baseUrl}/api/payment/request-payment", [
                'item_name'   => "Commande MACIF #{$commande->id}",
                'item_price'  => $commande->montant_total,
                'currency'    => 'XOF',
                'ref_command' => $reference,
                'command_name'=> "Commande #{$commande->id}",
                'env'         => config('services.paytech.env', 'test'),
                'ipn_url'     => config('app.url') . '/api/paiements/webhook',
                'success_url' => config('app.url') . '/paiement/succes',
                'cancel_url'  => config('app.url') . '/paiement/annule',
            ]);

            if ($response->successful() && isset($response['token'])) {
                return [
                    'success'     => true,
                    'payment_url' => "https://paytech.sn/payment/checkout/{$response['token']}",
                    'reference'   => $reference,
                    'message'     => 'Paiement initié avec succès.',
                ];
            }

            return ['success' => false, 'payment_url' => null, 'reference' => $reference, 'message' => 'Erreur PayTech.'];

        } catch (\Exception $e) {
            return ['success' => false, 'payment_url' => null, 'reference' => $reference, 'message' => 'Service indisponible.'];
        }
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-02 — Initier paiement abonnement
    // ══════════════════════════════════════════════════════════════

    /**
     * Initie un paiement PayTech pour un abonnement éleveur.
     *
     * Webhook de retour : POST /api/paiements/webhook-abonnement
     * (différent du webhook commandes pour éviter les confusions)
     *
     * @param  User        $eleveur
     * @param  Abonnement  $abonnement
     * @return array{success: bool, payment_url: string|null, message: string}
     */
    public function initierPaiementAbonnement(User $eleveur, Abonnement $abonnement): array
    {
        try {
            $response = Http::withHeaders([
                'API_KEY'    => $this->apiKey,
                'API_SECRET' => $this->apiSecret,
            ])->post("{$this->baseUrl}/api/payment/request-payment", [
                'item_name'   => "Abonnement MACIF {$abonnement->plan} — {$eleveur->name}",
                'item_price'  => $abonnement->prix_mensuel,
                'currency'    => 'XOF',
                'ref_command' => $abonnement->reference_paiement,
                'command_name'=> "Abonnement {$abonnement->plan} — 30 jours",
                'env'         => config('services.paytech.env', 'test'),
                'ipn_url'     => config('app.url') . '/api/paiements/webhook-abonnement',
                'success_url' => config('app.url') . '/abonnement/succes',
                'cancel_url'  => config('app.url') . '/abonnement/annule',
            ]);

            if ($response->successful() && isset($response['token'])) {
                Log::info('[ABO-02] Paiement abonnement initié', [
                    'eleveur_id'   => $eleveur->id,
                    'plan'         => $abonnement->plan,
                    'reference'    => $abonnement->reference_paiement,
                ]);

                return [
                    'success'     => true,
                    'payment_url' => "https://paytech.sn/payment/checkout/{$response['token']}",
                    'message'     => 'Paiement abonnement initié.',
                ];
            }

            return ['success' => false, 'payment_url' => null, 'message' => 'Erreur PayTech abonnement.'];

        } catch (\Exception $e) {
            Log::error('[ABO-02] Exception PayTech abonnement', ['error' => $e->getMessage()]);
            return ['success' => false, 'payment_url' => null, 'message' => 'Service indisponible.'];
        }
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-02 — Vérifier signature HMAC webhook
    // ══════════════════════════════════════════════════════════════

    public function verifierSignatureHmac(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha256', $payload, $this->apiSecret);
        return hash_equals($expected, $signature);
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-03 — Confirmer paiement commande après webhook
    // ══════════════════════════════════════════════════════════════

    public function confirmerPaiement(string $reference, array $webhookData): bool
    {
        $paiement = Paiement::where('reference_transaction', $reference)->first();

        if (!$paiement) {
            Log::warning('[PAY-03] Paiement introuvable', ['reference' => $reference]);
            return false;
        }

        if ($paiement->statut === 'confirme') {
            return true; // idempotent
        }

        $paiement->update(['statut' => 'confirme', 'webhook_data' => $webhookData]);
        $paiement->commande->update(['statut_paiement' => 'paye']);

        Log::info('[PAY-03] Paiement confirmé', ['commande_id' => $paiement->commande_id]);
        return true;
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-02 — Confirmer paiement abonnement après webhook
    // ══════════════════════════════════════════════════════════════

    /**
     * Active l'abonnement après confirmation du paiement PayTech.
     *
     * @param  string $reference   ref_command reçue dans le webhook
     * @param  array  $webhookData Données complètes du webhook
     * @return bool
     */
    public function confirmerPaiementAbonnement(string $reference, array $webhookData): bool
    {
        $abonnement = Abonnement::where('reference_paiement', $reference)->first();

        if (!$abonnement) {
            Log::warning('[ABO-02] Abonnement introuvable pour référence', ['reference' => $reference]);
            return false;
        }

        if ($abonnement->statut === 'actif') {
            Log::info('[ABO-02] Abonnement déjà actif — doublon ignoré', ['reference' => $reference]);
            return true; // idempotent
        }

        $abonnement->update(['statut' => 'actif']);

        Log::info('[ABO-02] Abonnement activé', [
            'eleveur_id' => $abonnement->eleveur_id,
            'plan'       => $abonnement->plan,
            'date_fin'   => $abonnement->date_fin,
        ]);

        return true;
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-05 — Remboursement via PayTech
    // ══════════════════════════════════════════════════════════════

    public function rembourserPaiement(Commande $commande): bool
    {
        $paiement = Paiement::where('commande_id', $commande->id)
            ->where('statut', 'confirme')
            ->first();

        if (!$paiement) return false;

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
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}