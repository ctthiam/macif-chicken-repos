<?php

namespace App\Jobs;

use App\Mail\ConfirmationPaiementMail;
use App\Models\Commande;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job : NTF-05 — Email confirmation paiement avec récapitulatif.
 *
 * Fichier : app/Jobs/EnvoyerEmailPaiementJob.php
 */
class EnvoyerEmailPaiementJob implements ShouldQueue
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
            Mail::to($commande->acheteur->email)
                ->send(new ConfirmationPaiementMail($commande));
        } catch (\Exception $e) {
            Log::error('[NTF-05] Erreur envoi email paiement', [
                'commande_id' => $commande->id,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}