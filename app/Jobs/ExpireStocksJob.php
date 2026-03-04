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
 * Job STK-07 : Expiration automatique des annonces de stock.
 *
 * Fichier : app/Jobs/ExpireStocksJob.php
 *
 * Deux critères d'expiration traités en une seule exécution :
 *
 *  1. DATE PÉREMPTION DÉPASSÉE (STK-07)
 *     Stocks dont `date_peremption_stock` <= aujourd'hui
 *     → statut passe à 'expire'
 *
 *  2. ABONNEMENT ÉLEVEUR EXPIRÉ (existant, amélioré)
 *     Éleveurs sans abonnement actif
 *     → stocks disponible/reserve passent à 'expire'
 *
 * Schedulé quotidiennement dans routes/console.php à 01:00 (après minuit).
 *
 * Note : Ce job REMPLACE ExpireStocksAbonnementJob qui devient obsolète.
 * Mettre à jour routes/console.php pour utiliser ExpireStocksJob.
 */
class ExpireStocksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 300; // 5 minutes entre chaque retry

    public function handle(): void
    {
        $today = now()->toDateString();

        // ── 1. STK-07 : Expirer par date de péremption ──────────────
        $expiredByDate = Stock::whereNotNull('date_peremption_stock')
            ->where('date_peremption_stock', '<', $today)
            ->whereIn('statut', ['disponible', 'reserve'])
            ->update(['statut' => 'expire']);

        Log::info('[STK-07] Stocks expirés par date_peremption', [
            'date'   => $today,
            'count'  => $expiredByDate,
        ]);

        // ── 2. Expirer les stocks d'éleveurs sans abonnement actif ──
        $eleveursSansAbonnement = User::where('role', 'eleveur')
            ->whereDoesntHave('abonnementActif')
            ->pluck('id');

        if ($eleveursSansAbonnement->isEmpty()) {
            Log::info('[STK-07] Aucun éleveur sans abonnement actif.');
            return;
        }

        $expiredByAbonnement = Stock::whereIn('eleveur_id', $eleveursSansAbonnement)
            ->whereIn('statut', ['disponible', 'reserve'])
            ->update(['statut' => 'expire']);

        Log::info('[STK-07] Stocks expirés (abonnement expiré)', [
            'eleveurs_count' => $eleveursSansAbonnement->count(),
            'stocks_expires' => $expiredByAbonnement,
        ]);
    }
}