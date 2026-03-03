<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory : User — génère des utilisateurs de test complets.
 * Inclut tous les champs NOT NULL : phone, role.
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'                     => fake()->name(),
            'email'                    => fake()->unique()->safeEmail(),
            'email_verified_at'        => now(),
            'password'                 => Hash::make('password'),
            'phone'                    => '+221' . fake()->unique()->numerify('7########'),
            'role'                     => 'acheteur', // défaut safe
            'avatar'                   => null,
            'adresse'                  => null,
            'ville'                    => null,
            'is_verified'              => true,
            'is_active'                => true,
            'email_verification_token' => null,
            'remember_token'           => Str::random(10),
        ];
    }

    /** State : éléveur */
    public function eleveur(): static
    {
        return $this->state(['role' => 'eleveur']);
    }

    /** State : acheteur */
    public function acheteur(): static
    {
        return $this->state(['role' => 'acheteur']);
    }

    /** State : admin */
    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    /** State : non vérifié */
    public function unverified(): static
    {
        return $this->state([
            'is_verified'       => false,
            'email_verified_at' => null,
        ]);
    }
}