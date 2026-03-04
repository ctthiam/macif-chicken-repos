<?php

namespace Database\Factories;

use App\Models\Abonnement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory : Abonnement
 * Fichier : database/factories/AbonnementFactory.php
 */
class AbonnementFactory extends Factory
{
    protected $model = Abonnement::class;

    public function definition(): array
    {
        return [
            'eleveur_id'        => User::factory()->eleveur(),
            'plan'              => 'starter',
            'prix_mensuel'      => Abonnement::PRIX['starter'],
            'date_debut'        => now()->toDateString(),
            'date_fin'          => now()->addMonth()->toDateString(),
            'statut'            => 'actif',
            'methode_paiement'  => 'wave',
            'reference_paiement'=> 'REF-' . fake()->unique()->numerify('########'),
        ];
    }

    public function starter(): static
    {
        return $this->state([
            'plan'         => 'starter',
            'prix_mensuel' => Abonnement::PRIX['starter'],
        ]);
    }

    public function pro(): static
    {
        return $this->state([
            'plan'         => 'pro',
            'prix_mensuel' => Abonnement::PRIX['pro'],
        ]);
    }

    public function premium(): static
    {
        return $this->state([
            'plan'         => 'premium',
            'prix_mensuel' => Abonnement::PRIX['premium'],
        ]);
    }

    public function expire(): static
    {
        return $this->state([
            'statut'   => 'expire',
            'date_fin' => now()->subDay()->toDateString(),
        ]);
    }
}