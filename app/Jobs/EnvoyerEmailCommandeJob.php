<?php

namespace App\Jobs;

use App\Mail\ConfirmationCommandeMail;
use App\Models\Commande;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job : NTF-04 — Email confirmation commande.
 *
 * Fichier : app/Jobs/EnvoyerEmailCommandeJob.php
 *
 * Envoie :
 *   - Un email à l'acheteur (confirmation de commande)
 *   - Un email à l'éleveur (notification nouvelle commande)
 */
class EnvoyerEmailCommandeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Commande $commande
    ) {}

    public function handle(): void
    {
        $commande = $this->commande->load(['eleveur', 'acheteur', 'stock']);

        try {
            // Email → acheteur
            Mail::to($commande->acheteur->email)
                ->send(new ConfirmationCommandeMail($commande, 'acheteur'));

            // Email → éleveur
            Mail::to($commande->eleveur->email)
                ->send(new ConfirmationCommandeMail($commande, 'eleveur'));

        } catch (\Exception $e) {
            Log::error('[NTF-04] Erreur envoi email commande', [
                'commande_id' => $commande->id,
                'error'       => $e->getMessage(),
            ]);
            throw $e; // retry
        }
    }
}