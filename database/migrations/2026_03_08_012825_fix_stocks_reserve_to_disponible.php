<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Correction : stocks avec statut 'reserve' qui ont encore
 * une quantite_disponible > 0 doivent repasser en 'disponible'.
 *
 * php artisan migrate
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE stocks
            SET statut = 'disponible'
            WHERE statut = 'reserve'
              AND quantite_disponible > 0
        ");
    }

    public function down(): void
    {
        // Pas de rollback logique possible sans snapshot
    }
};