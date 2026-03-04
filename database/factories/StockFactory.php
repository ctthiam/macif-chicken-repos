<?php

namespace Database\Factories;

use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory : Stock
 * Fichier : database/factories/StockFactory.php
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function definition(): array
    {
        return [
            'eleveur_id'          => User::factory()->eleveur(),
            'titre'               => fake()->words(3, true) . ' fermier',
            'description'         => fake()->paragraph(),
            'quantite_disponible' => fake()->numberBetween(10, 200),
            'poids_moyen_kg'      => fake()->randomFloat(2, 1.5, 3.5),
            'prix_par_kg'         => fake()->randomFloat(0, 1500, 3500),
            'prix_par_unite'      => fake()->randomFloat(0, 2500, 5000),
            'mode_vente'          => fake()->randomElement(['vivant', 'abattu', 'les_deux']),
            'date_disponibilite'  => now()->toDateString(),
            'date_peremption_stock' => now()->addDays(14)->toDateString(),
            'photos'              => [],
            'statut'              => 'disponible',
            'vues'                => 0,
        ];
    }

    public function disponible(): static
    {
        return $this->state(['statut' => 'disponible']);
    }

    public function epuise(): static
    {
        return $this->state(['statut' => 'epuise', 'quantite_disponible' => 0]);
    }

    public function expire(): static
    {
        return $this->state(['statut' => 'expire']);
    }
}