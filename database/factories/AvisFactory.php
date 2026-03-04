<?php

namespace Database\Factories;

use App\Models\Avis;
use App\Models\Commande;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory : Avis
 * Fichier : database/factories/AvisFactory.php
 */
class AvisFactory extends Factory
{
    protected $model = Avis::class;

    public function definition(): array
    {
        return [
            'commande_id' => Commande::factory(), // NOT NULL en base
            'auteur_id'   => User::factory()->acheteur(),
            'cible_id'    => User::factory()->eleveur(),
            'note'        => fake()->numberBetween(1, 5),
            'commentaire' => fake()->paragraph(),
            'reply'       => null,
            'is_reported' => false,
        ];
    }

    public function reported(): static
    {
        return $this->state(['is_reported' => true]);
    }

    public function withReply(): static
    {
        return $this->state(['reply' => fake()->sentence()]);
    }
}