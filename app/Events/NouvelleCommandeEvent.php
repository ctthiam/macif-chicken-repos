<?php

namespace App\Events;

use App\Models\Commande;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event : NTF-01 — Nouvelle commande passée.
 *
 * Fichier : app/Events/NouvelleCommandeEvent.php
 *
 * Déclenché dans : Shared\CommandeSharedController::store()
 * Écouté par     : NouvelleCommandeListener
 */
class NouvelleCommandeEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Commande $commande
    ) {}
}