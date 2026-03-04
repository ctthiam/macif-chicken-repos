<?php

namespace Database\Factories;

use App\Models\Abonnement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fichier : database/factories/AbonnementFactory.php
 */
class AbonnementFactory extends Factory
{
    protected $model = Abonnement::class;

    public function definition(): array
    {
        $plan      = $this->faker->randomElement(['starter', 'pro', 'premium']);
        $dateDebut = now();

        return [
            'eleveur_id'        => User::factory()->eleveur(),
            'plan'              => $plan,
            'prix_mensuel'      => Abonnement::PRIX[$plan],
            'date_debut'        => $dateDebut,
            'date_fin'          => $dateDebut->copy()->addDays(Abonnement::DUREE_JOURS),
            'statut'            => 'actif',
            'methode_paiement'  => $this->faker->randomElement(['wave', 'orange_money', 'free_money']),
            'reference_paiement'=> 'ABO-' . strtoupper($this->faker->bothify('??####')),
        ];
    }

    // ── States ───────────────────────────────────────────────────

    public function starter(): static
    {
        return $this->state(fn () => [
            'plan'         => 'starter',
            'prix_mensuel' => Abonnement::PRIX['starter'],
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'plan'         => 'pro',
            'prix_mensuel' => Abonnement::PRIX['pro'],
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn () => [
            'plan'         => 'premium',
            'prix_mensuel' => Abonnement::PRIX['premium'],
        ]);
    }

    public function actif(): static
    {
        return $this->state(fn () => [
            'statut'   => 'actif',
            'date_fin' => now()->addDays(15),
        ]);
    }

    public function expire(): static
    {
        return $this->state(fn () => [
            'statut'   => 'expire',
            'date_fin' => now()->subDays(5),
        ]);
    }

    public function expirationProche(): static
    {
        return $this->state(fn () => [
            'statut'   => 'actif',
            'date_fin' => now()->addDays(3), // dans 3 jours — < 5 jours
        ]);
    }
}