<?php

namespace App\Events;

use App\Models\Commande;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event : NTF-02 — Paiement confirmé (webhook PayTech).
 *
 * Fichier : app/Events/PaiementConfirmeEvent.php
 *
 * Déclenché dans : PaiementService::confirmerPaiement()
 * Écouté par     : PaiementConfirmeListener
 */
class PaiementConfirmeEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Commande $commande
    ) {}
}