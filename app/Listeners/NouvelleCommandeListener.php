<?php

namespace App\Listeners;

use App\Events\NouvelleCommandeEvent;
use App\Jobs\EnvoyerEmailCommandeJob;
use App\Jobs\EnvoyerSmsJob;
use App\Services\NotificationService;

/**
 * Listener : NTF-01 + NTF-04 + NTF-06
 *
 * Fichier : app/Listeners/NouvelleCommandeListener.php
 *
 * Réponse à NouvelleCommandeEvent :
 *   NTF-01 : Notification in-app → éleveur
 *   NTF-04 : Email confirmation → acheteur + éleveur (Job queue)
 *   NTF-06 : SMS urgence → éleveur (Job queue)
 */
class NouvelleCommandeListener
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function handle(NouvelleCommandeEvent $event): void
    {
        $commande = $event->commande->load(['eleveur', 'acheteur', 'stock']);

        // ── NTF-01 : Notification in-app éleveur ─────────────────
        $this->notificationService->notifier(
            userId:  $commande->eleveur_id,
            titre:   'Nouvelle commande reçue',
            message: "Vous avez reçu une commande de {$commande->acheteur->name} "
                   . "pour {$commande->quantite} unité(s) — {$commande->montant_total} FCFA.",
            type:    'new_order',
            data:    [
                'commande_id' => $commande->id,
                'acheteur'    => $commande->acheteur->name,
                'montant'     => $commande->montant_total,
            ]
        );

        // ── NTF-04 : Email confirmation → acheteur + éleveur ─────
        EnvoyerEmailCommandeJob::dispatch($commande);

        // ── NTF-06 : SMS urgence → éleveur ───────────────────────
        if ($commande->eleveur->phone ?? null) {
            EnvoyerSmsJob::dispatch(
                to:      $commande->eleveur->phone,
                message: "MACIF CHICKEN : Nouvelle commande #{$commande->id} de {$commande->acheteur->name}. Montant : {$commande->montant_total} FCFA."
            );
        }
    }
}