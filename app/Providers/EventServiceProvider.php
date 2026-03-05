<?php

namespace App\Providers;

use App\Events\NouvelleCommandeEvent;
use App\Events\PaiementConfirmeEvent;
use App\Events\StatutCommandeEvent;
use App\Listeners\NouvelleCommandeListener;
use App\Listeners\PaiementConfirmeListener;
use App\Listeners\StatutCommandeListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Fichier : app/Providers/EventServiceProvider.php
 *
 * Enregistrement des Events → Listeners pour les notifications.
 */
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // NTF-01 + NTF-04 + NTF-06
        NouvelleCommandeEvent::class => [
            NouvelleCommandeListener::class,
        ],

        // NTF-02 + NTF-05
        PaiementConfirmeEvent::class => [
            PaiementConfirmeListener::class,
        ],

        // NTF-03 + NTF-07
        StatutCommandeEvent::class => [
            StatutCommandeListener::class,
        ],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}