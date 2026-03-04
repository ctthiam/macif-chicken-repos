<?php

namespace Database\Factories;

use App\Models\Commande;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory : Commande
 * Fichier : database/factories/CommandeFactory.php
 */
class CommandeFactory extends Factory
{
    protected $model = Commande::class;

    public function definition(): array
    {
        $montant    = fake()->numberBetween(5000, 50000);
        $commission = intval($montant * 0.07);
        $eleveur    = User::factory()->eleveur()->create();

        return [
            'acheteur_id'           => User::factory()->acheteur(),
            'eleveur_id'            => $eleveur->id,
            'stock_id'              => Stock::factory()->create(['eleveur_id' => $eleveur->id])->id,
            'quantite'              => fake()->numberBetween(1, 20),
            'poids_total'           => fake()->randomFloat(2, 1, 50),
            'montant_total'         => $montant,
            'commission_plateforme' => $commission,
            'montant_eleveur'       => $montant - $commission,
            'mode_paiement'         => 'wave',
            'statut_paiement'       => 'paye',
            'statut_commande'       => 'livree',
            'adresse_livraison'     => fake()->address(),
        ];
    }
}