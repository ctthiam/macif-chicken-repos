<?php

// routes/console.php — Scheduler MACIF CHICKEN

use App\Jobs\ExpireStocksJob;
use App\Jobs\LibererEscrowJob;
use Illuminate\Support\Facades\Schedule;

// Libération escrow auto : toutes les heures
Schedule::job(new LibererEscrowJob)->hourly();

// Expiration stocks (date péremption + abonnement expiré) : chaque jour à 01h00
Schedule::job(new ExpireStocksJob)->dailyAt('01:00');