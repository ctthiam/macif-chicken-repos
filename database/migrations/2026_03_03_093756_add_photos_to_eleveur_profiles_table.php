<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne photos (JSON) à eleveur_profiles.
 * Stocke un tableau d'URLs des photos du poulailler.
 *
 * Fichier : database/migrations/xxxx_add_photos_to_eleveur_profiles_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleveur_profiles', function (Blueprint $table) {
            $table->json('photos')->nullable()->after('nombre_avis');
        });
    }

    public function down(): void
    {
        Schema::table('eleveur_profiles', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};