<?php

namespace App\Events;

use App\Models\Commande;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event : NTF-03 — Changement de statut d'une commande.
 *
 * Fichier : app/Events/StatutCommandeEvent.php
 *
 * Déclenché dans : Shared\CommandeSharedController (update statut)
 * Écouté par     : StatutCommandeListener
 */
class StatutCommandeEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Commande $commande,
        public readonly string   $ancienStatut
    ) {}
}