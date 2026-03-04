<?php

namespace App\Services;

use App\Models\Commande;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service : Gestion de l'escrow (séquestre des fonds)
 * Les fonds sont libérés à l'éleveur après confirmation livraison ou 48h auto.
 *
 * Intégration paiement : NabooPay (Sprint PAY)
 * NabooPay supporte nativement Wave, Orange Money, Free Money.
 * Doc API : https://naboopay.com/documentation
 *
 * Fichier : app/Services/EscrowService.php
 */
class EscrowService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Libère les fonds escrow vers l'éleveur.
     * Met à jour statut_paiement = 'libere' + escrow_libere_at.
     *
     * @param  Commande $commande
     * @return void
     * @throws \Exception si paiement déjà libéré ou non payé
     */
    public function liberer(Commande $commande): void
    {
        if ($commande->statut_paiement !== 'paye') {
            throw new \Exception("Impossible de libérer : paiement statut = {$commande->statut_paiement}");
        }

        DB::transaction(function () use ($commande) {
            $commande->update([
                'statut_paiement'  => 'libere',
                'escrow_libere_at' => now(),
            ]);

            // TODO Sprint PAY : Virement NabooPay vers l'éleveur
            // POST https://api.naboopay.com/api/v1/transaction/transfer
            // Body : { amount: commande->montant_eleveur, recipient: eleveur->naboopay_account }

            $this->notificationService->notifier(
                userId:  $commande->eleveur_id,
                titre:   'Fonds reçus',
                message: "Les fonds de la commande #{$commande->id} ont été virés sur votre compte.",
                type:    'payment',
                data:    ['commande_id' => $commande->id, 'montant' => $commande->montant_eleveur]
            );
        });
    }

    /**
     * Rembourse l'acheteur (annulation ou décision admin).
     *
     * @param  Commande $commande
     * @return void
     */
    public function rembourser(Commande $commande): void
    {
        DB::transaction(function () use ($commande) {
            $commande->update(['statut_paiement' => 'rembourse']);

            // TODO Sprint PAY : Remboursement NabooPay
            // POST https://api.naboopay.com/api/v1/transaction/refund
            // Body : { transaction_id: commande->naboo_transaction_id }

            $this->notificationService->notifier(
                userId:  $commande->acheteur_id,
                titre:   'Remboursement effectué',
                message: "Votre commande #{$commande->id} a été remboursée.",
                type:    'payment',
                data:    ['commande_id' => $commande->id, 'montant' => $commande->montant_total]
            );
        });
    }
}