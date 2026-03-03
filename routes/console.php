<?php

// routes/console.php — Scheduler MACIF CHICKEN

use App\Jobs\LibererEscrowJob;
use App\Jobs\ExpireStocksAbonnementJob;
use Illuminate\Support\Facades\Schedule;

// Libération escrow auto : toutes les heures
Schedule::job(new LibererEscrowJob)->hourly();

// Expiration stocks si abonnement expiré : chaque jour à minuit
Schedule::job(new ExpireStocksAbonnementJob)->dailyAt('00:00');