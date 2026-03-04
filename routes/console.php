<?php

// routes/console.php — Scheduler MACIF CHICKEN

use App\Jobs\AlerteExpirationAbonnementJob;
use App\Jobs\ExpireStocksAbonnementJob;
use App\Jobs\LibererEscrowJob;
use Illuminate\Support\Facades\Schedule;

// PAY-04 : Libération escrow auto : toutes les heures
Schedule::job(new LibererEscrowJob)->hourly();

// ABO-04 : Expiration stocks si abonnement expiré : chaque jour à 00h00
Schedule::job(ExpireStocksAbonnementJob::class)->dailyAt('00:00');

// ABO-03 : Alerte expiration abonnement 5 jours avant : chaque jour à 08h00
Schedule::job(AlerteExpirationAbonnementJob::class)->dailyAt('08:00');