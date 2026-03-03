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
 * Job : Libère automatiquement les fonds escrow après 48h
 * si l'acheteur n'a pas ouvert de litige.
 * Schedulé dans routes/console.php (toutes les heures).
 */
class LibererEscrowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(EscrowService $escrowService): void
    {
        $heures = (int) env('ESCROW_LIBERATION_HOURS', 48);

        // Commandes livrées depuis plus de 48h, paiement = 'paye', pas de litige actif
        $commandes = Commande::query()
            ->where('statut_commande', 'livree')
            ->where('statut_paiement', 'paye')
            ->whereNull('escrow_libere_at')
            ->whereDoesntHave('litige', fn ($q) => $q->whereIn('statut', ['ouvert', 'en_cours']))
            ->where('updated_at', '<=', now()->subHours($heures))
            ->get();

        foreach ($commandes as $commande) {
            try {
                $escrowService->liberer($commande);
                Log::info("Escrow libéré automatiquement", ['commande_id' => $commande->id]);
            } catch (\Exception $e) {
                Log::error("Erreur libération escrow", [
                    'commande_id' => $commande->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }
}