<?php

namespace App\Listeners;

use App\Events\PaiementConfirmeEvent;
use App\Jobs\EnvoyerEmailPaiementJob;
use App\Services\NotificationService;

/**
 * Listener : NTF-02 + NTF-05
 *
 * Fichier : app/Listeners/PaiementConfirmeListener.php
 *
 * Réponse à PaiementConfirmeEvent :
 *   NTF-02 : Notification in-app paiement reçu → éleveur
 *   NTF-05 : Email récapitulatif paiement → acheteur (Job queue)
 */
class PaiementConfirmeListener
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function handle(PaiementConfirmeEvent $event): void
    {
        $commande = $event->commande->load(['eleveur', 'acheteur', 'stock']);

        // ── NTF-02 : Notification in-app éleveur ─────────────────
        $this->notificationService->notifier(
            userId:  $commande->eleveur_id,
            titre:   'Paiement reçu',
            message: "Le paiement de {$commande->acheteur->name} a été confirmé. "
                   . "Montant en escrow : {$commande->montant_total} FCFA.",
            type:    'payment',
            data:    [
                'commande_id' => $commande->id,
                'montant'     => $commande->montant_total,
            ]
        );

        // ── NTF-05 : Email récapitulatif paiement → acheteur ─────
        EnvoyerEmailPaiementJob::dispatch($commande);
    }
}