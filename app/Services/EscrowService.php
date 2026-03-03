<?php

namespace App\Services;

use App\Models\Commande;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service : Gestion de l'escrow (séquestre des fonds)
 * Les fonds sont libérés à l'éleveur après confirmation livraison ou 48h auto.
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
     * @param Commande $commande
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
                'statut_paiement' => 'libere',
                'escrow_libere_at' => now(),
            ]);

            // TODO: Appel API PayTech pour virement vers éleveur
            // $this->paytech->transfert($commande->montant_eleveur, $commande->eleveur);

            // Notifier l'éleveur
            $this->notificationService->notifier(
                userId: $commande->eleveur_id,
                titre: 'Fonds reçus',
                message: "Les fonds de la commande #{$commande->id} ont été virés sur votre compte.",
                type: 'payment',
                data: ['commande_id' => $commande->id, 'montant' => $commande->montant_eleveur]
            );
        });
    }

    /**
     * Rembourse l'acheteur (décision admin uniquement via PayTech refund).
     *
     * @param Commande $commande
     * @return void
     */
    public function rembourser(Commande $commande): void
    {
        DB::transaction(function () use ($commande) {
            $commande->update(['statut_paiement' => 'rembourse']);

            // TODO: Appel API PayTech refund
            // $this->paytech->remboursement($commande->reference_transaction);

            $this->notificationService->notifier(
                userId: $commande->acheteur_id,
                titre: 'Remboursement effectué',
                message: "Votre commande #{$commande->id} a été remboursée.",
                type: 'payment',
                data: ['commande_id' => $commande->id, 'montant' => $commande->montant_total]
            );
        });
    }
}