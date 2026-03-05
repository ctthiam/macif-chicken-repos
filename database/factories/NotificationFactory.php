<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fichier : database/factories/NotificationFactory.php
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->acheteur(),
            'titre'   => $this->faker->sentence(4),
            'message' => $this->faker->sentence(10),
            'type'    => $this->faker->randomElement(['new_order', 'payment', 'delivery', 'review', 'system', 'subscription']),
            'is_read' => false,
            'data'    => null,
        ];
    }
}