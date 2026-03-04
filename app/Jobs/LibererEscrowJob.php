<?php

namespace App\Jobs;

use App\Models\Commande;
use App\Services\EscrowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job CMD-07 : Libération automatique des fonds escrow après 48h.
 *
 * Fichier : app/Jobs/LibererEscrowJob.php
 *
 * Déclenché :
 *   - Automatiquement : schedulé toutes les heures dans routes/console.php
 *   - Manuellement : dispatch(new LibererEscrowJob()) depuis CMD-06 (si acheteur
 *     ne confirme pas dans les 48h)
 *
 * Conditions de libération :
 *   1. statut_commande = 'livree'
 *   2. statut_paiement = 'paye'
 *   3. escrow_libere_at IS NULL
 *   4. Aucun litige actif (statut ouvert ou en_cours)
 *   5. updated_at <= now() - ESCROW_LIBERATION_HOURS (défaut 48h)
 *
 * Note : la confirmation acheteur (CMD-06) libère immédiatement sans attendre 48h.
 * Ce job traite uniquement les cas où l'acheteur ne confirme pas.
 */
class LibererEscrowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 300;

    public function handle(EscrowService $escrowService): void
    {
        $heures = (int) env('ESCROW_LIBERATION_HOURS', 48);

        $commandes = Commande::query()
            ->where('statut_commande', 'livree')
            ->where('statut_paiement', 'paye')
            ->whereNull('escrow_libere_at')
            ->whereDoesntHave('litige', fn ($q) => $q->whereIn('statut', ['ouvert', 'en_cours']))
            ->where('updated_at', '<=', now()->subHours($heures))
            ->get();

        if ($commandes->isEmpty()) {
            Log::info('[CMD-07] Aucune commande éligible à la libération escrow.');
            return;
        }

        $liberees = 0;
        $erreurs  = 0;

        foreach ($commandes as $commande) {
            try {
                $escrowService->liberer($commande);
                $liberees++;
                Log::info('[CMD-07] Escrow libéré automatiquement', ['commande_id' => $commande->id]);
            } catch (\Exception $e) {
                $erreurs++;
                Log::error('[CMD-07] Erreur libération escrow', [
                    'commande_id' => $commande->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        Log::info('[CMD-07] Bilan libération escrow', [
            'total_eligibles' => $commandes->count(),
            'liberees'        => $liberees,
            'erreurs'         => $erreurs,
        ]);
    }
}