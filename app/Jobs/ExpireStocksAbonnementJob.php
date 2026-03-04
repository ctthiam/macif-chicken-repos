<?php

namespace App\Jobs;

use App\Models\Abonnement;
use App\Models\Stock;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job : ABO-04 — Suspension stocks si abonnement expiré.
 *
 * Fichier : app/Jobs/ExpireStocksAbonnementJob.php
 *
 * Schedulé : quotidien à 00h00 dans routes/console.php
 *
 * Logique :
 *   1. Trouve les abonnements actifs dont date_fin < aujourd'hui
 *      → les marque 'expire'
 *   2. Trouve les éleveurs sans aucun abonnement actif valide
 *      → expire leurs stocks disponibles/reserve
 *   3. Envoie une notification in-app à chaque éleveur affecté
 */
class ExpireStocksAbonnementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private NotificationService $notificationService;

    public function handle(NotificationService $notificationService): void
    {
        $this->notificationService = $notificationService;
        // ── Étape 1 : Marquer les abonnements expirés ─────────────
        $abonnementsExpires = Abonnement::where('statut', 'actif')
            ->where('date_fin', '<', now()->startOfDay())
            ->get();

        foreach ($abonnementsExpires as $abonnement) {
            $abonnement->update(['statut' => 'expire']);
        }

        if ($abonnementsExpires->isNotEmpty()) {
            Log::info('[ABO-04] Abonnements expirés mis à jour', [
                'count' => $abonnementsExpires->count(),
            ]);
        }

        // ── Étape 2 : Trouver les éleveurs sans abonnement actif ──
        $eleveursSansAbonnement = User::where('role', 'eleveur')
            ->whereDoesntHave('abonnementActif')
            ->pluck('id');

        if ($eleveursSansAbonnement->isEmpty()) {
            Log::info('[ABO-04] Aucun éleveur sans abonnement actif.');
            return;
        }

        // ── Étape 3 : Expirer leurs stocks actifs ─────────────────
        $stocksAExpirer = Stock::whereIn('eleveur_id', $eleveursSansAbonnement)
            ->whereIn('statut', ['disponible', 'reserve'])
            ->get();

        $stocksExpires = 0;
        $eleveursNotifies = [];

        foreach ($stocksAExpirer as $stock) {
            $stock->update(['statut' => 'expire']);
            $stocksExpires++;

            // Notifier l'éleveur une seule fois (même si plusieurs stocks)
            if (!in_array($stock->eleveur_id, $eleveursNotifies)) {
                try {
                    $this->notificationService->notifier(
                        userId:  $stock->eleveur_id,
                        titre:   'Stocks suspendus',
                        message: 'Votre abonnement a expiré. Vos stocks ont été suspendus. '
                               . 'Renouvelez votre abonnement pour les remettre en ligne.',
                        type:    'subscription',
                        data:    ['eleveur_id' => $stock->eleveur_id]
                    );
                    $eleveursNotifies[] = $stock->eleveur_id;
                } catch (\Exception $e) {
                    Log::error('[ABO-04] Erreur notification', [
                        'eleveur_id' => $stock->eleveur_id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('[ABO-04] Stocks expirés (abonnement expiré)', [
            'eleveurs_affectes' => $eleveursSansAbonnement->count(),
            'stocks_expires'    => $stocksExpires,
            'eleveurs_notifies' => count($eleveursNotifies),
        ]);
    }
}