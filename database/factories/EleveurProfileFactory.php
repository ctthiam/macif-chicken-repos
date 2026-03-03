<?php

namespace Database\Factories;

use App\Models\EleveurProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory : EleveurProfile
 *
 * Fichier : database/factories/EleveurProfileFactory.php
 */
class EleveurProfileFactory extends Factory
{
    protected $model = EleveurProfile::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory()->eleveur(),
            'nom_poulailler' => fake()->company() . ' Ferme',
            'description'    => fake()->paragraph(),
            'localisation'   => fake()->address(),
            'latitude'       => fake()->latitude(12, 15),   // zone Sénégal
            'longitude'      => fake()->longitude(-17, -11),
            'is_certified'   => false,
            'note_moyenne'   => 0,
            'nombre_avis'    => 0,
            'photos'         => [],
        ];
    }

    /** State : éleveur certifié */
    public function certified(): static
    {
        return $this->state(['is_certified' => true]);
    }

    /** State : avec note */
    public function withNote(float $note, int $nombreAvis = 5): static
    {
        return $this->state([
            'note_moyenne' => $note,
            'nombre_avis'  => $nombreAvis,
        ]);
    }
}