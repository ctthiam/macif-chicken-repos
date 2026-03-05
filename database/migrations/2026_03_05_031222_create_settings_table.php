<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : Table settings (clé/valeur)
 *
 * Fichier : database/migrations/xxxx_create_settings_table.php
 *
 * Clés prédéfinies :
 *   taux_commission  (float, ex: 0.07 = 7%)
 *   starter_prix     (int FCFA)
 *   pro_prix         (int FCFA)
 *   premium_prix     (int FCFA)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique();
            $table->text('valeur');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Valeurs par défaut
        DB::table('settings')->insert([
            ['cle' => 'taux_commission', 'valeur' => '0.07',  'description' => 'Taux de commission plateforme (ex: 0.07 = 7%)', 'created_at' => now(), 'updated_at' => now()],
            ['cle' => 'starter_prix',   'valeur' => '5000',   'description' => 'Prix plan Starter en FCFA/mois',                'created_at' => now(), 'updated_at' => now()],
            ['cle' => 'pro_prix',       'valeur' => '15000',  'description' => 'Prix plan Pro en FCFA/mois',                    'created_at' => now(), 'updated_at' => now()],
            ['cle' => 'premium_prix',   'valeur' => '30000',  'description' => 'Prix plan Premium en FCFA/mois',                'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};