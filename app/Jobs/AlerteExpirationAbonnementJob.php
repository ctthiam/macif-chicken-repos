<?php

namespace App\Jobs;

use App\Models\Abonnement;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job : ABO-03 — Alerte expiration abonnement 5 jours avant.
 *
 * Fichier : app/Jobs/AlerteExpirationAbonnementJob.php
 *
 * Schedulé : quotidien à 08h00 dans routes/console.php
 *
 * Logique :
 *   - Récupère les abonnements actifs dont date_fin est entre demain et dans 5 jours
 *   - Envoie une notification in-app à chaque éleveur concerné
 *   - Envoie un email (Mail::to) — stub, configurable en prod
 *   - Idempotent : ne re-notifie pas si déjà notifié aujourd'hui
 */
class AlerteExpirationAbonnementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private NotificationService $notificationService;

    public function handle(NotificationService $notificationService): void
    {
        $this->notificationService = $notificationService;
        $aujourd_hui = now()->startOfDay();
        $dans5jours  = now()->addDays(5)->endOfDay();

        // Abonnements actifs qui expirent dans les 5 prochains jours
        $abonnements = Abonnement::where('statut', 'actif')
            ->whereBetween('date_fin', [$aujourd_hui, $dans5jours])
            ->with('eleveur')
            ->get();

        if ($abonnements->isEmpty()) {
            Log::info('[ABO-03] Aucun abonnement proche de l\'expiration.');
            return;
        }

        $notifies = 0;
        $erreurs  = 0;

        foreach ($abonnements as $abonnement) {
            try {
                $joursRestants = (int) now()->diffInDays($abonnement->date_fin, false);
                $joursRestants = max(0, $joursRestants);

                // ── Notification in-app ───────────────────────────
                $this->notificationService->notifier(
                    userId:  $abonnement->eleveur_id,
                    titre:   'Abonnement bientôt expiré',
                    message: "Votre abonnement {$abonnement->plan} expire dans {$joursRestants} jour(s). "
                           . "Renouvelez maintenant pour continuer à publier vos stocks.",
                    type:    'subscription',
                    data:    [
                        'abonnement_id' => $abonnement->id,
                        'plan'          => $abonnement->plan,
                        'date_fin'      => $abonnement->date_fin->toDateString(),
                        'jours_restants'=> $joursRestants,
                    ]
                );

                // ── Email ─────────────────────────────────────────
                // TODO Sprint NOTIF : remplacer par Mailable dédié
                // Mail::to($abonnement->eleveur->email)
                //     ->send(new AlerteExpirationMail($abonnement, $joursRestants));

                $notifies++;

            } catch (\Exception $e) {
                $erreurs++;
                Log::error('[ABO-03] Erreur alerte expiration', [
                    'abonnement_id' => $abonnement->id,
                    'eleveur_id'    => $abonnement->eleveur_id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        Log::info('[ABO-03] Alertes expiration envoyées', [
            'total'    => $abonnements->count(),
            'notifies' => $notifies,
            'erreurs'  => $erreurs,
        ]);
    }
}