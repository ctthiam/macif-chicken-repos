<?php

namespace App\Listeners;

use App\Events\StatutCommandeEvent;
use App\Services\NotificationService;

/**
 * Listener : NTF-03 + NTF-07
 *
 * Fichier : app/Listeners/StatutCommandeListener.php
 *
 * Réponse à StatutCommandeEvent :
 *   NTF-03 : Notification in-app changement statut → acheteur
 *   NTF-07 : Alerte stock bientôt épuisé → éleveur (si quantite < 10)
 */
class StatutCommandeListener
{
    // Labels lisibles pour l'acheteur
    private const LABELS = [
        'en_preparation' => 'En préparation',
        'en_livraison'   => 'En cours de livraison',
        'livree'         => 'Livrée',
        'annulee'        => 'Annulée',
        'litige'         => 'En litige',
    ];

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function handle(StatutCommandeEvent $event): void
    {
        $commande    = $event->commande->load(['eleveur', 'acheteur', 'stock']);
        $nouveauStatut = $commande->statut_commande;
        $label         = self::LABELS[$nouveauStatut] ?? $nouveauStatut;

        // ── NTF-03 : Notification in-app → acheteur ──────────────
        $this->notificationService->notifier(
            userId:  $commande->acheteur_id,
            titre:   "Commande #{$commande->id} mise à jour",
            message: "Votre commande chez {$commande->eleveur->name} est maintenant : {$label}.",
            type:    'delivery',
            data:    [
                'commande_id'    => $commande->id,
                'nouveau_statut' => $nouveauStatut,
                'ancien_statut'  => $event->ancienStatut,
            ]
        );

        // ── NTF-07 : Alerte stock bientôt épuisé → éleveur ───────
        $stock = $commande->stock;
        if ($stock && $stock->quantite_disponible < 10 && $stock->quantite_disponible > 0) {
            $this->notificationService->notifier(
                userId:  $commande->eleveur_id,
                titre:   'Stock bientôt épuisé',
                message: "Votre stock « {$stock->titre} » n'a plus que {$stock->quantite_disponible} unité(s) disponible(s).",
                type:    'system',
                data:    [
                    'stock_id'            => $stock->id,
                    'quantite_disponible' => $stock->quantite_disponible,
                ]
            );
        }
    }
}