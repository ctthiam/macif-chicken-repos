<?php

namespace App\Jobs;

use App\Models\Stock;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job : Masque les stocks des éleveurs dont l'abonnement a expiré.
 * Schedulé daily dans routes/console.php.
 */
class ExpireStocksAbonnementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Éleveurs sans abonnement actif
        $eleveursSansAbonnement = User::where('role', 'eleveur')
            ->whereDoesntHave('abonnementActif')
            ->pluck('id');

        if ($eleveursSansAbonnement->isEmpty()) {
            return;
        }

        $updated = Stock::whereIn('eleveur_id', $eleveursSansAbonnement)
            ->whereIn('statut', ['disponible', 'reserve'])
            ->update(['statut' => 'expire']);

        Log::info("Stocks expirés (abonnement expiré)", [
            'eleveurs_count' => $eleveursSansAbonnement->count(),
            'stocks_expires' => $updated,
        ]);
    }
}