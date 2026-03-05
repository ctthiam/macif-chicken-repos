<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration : Ajouter 'masque' à l'enum statut de la table stocks.
 *
 * Fichier : database/migrations/2026_03_05_000002_add_masque_to_stocks_statut.php
 *
 * Nécessaire pour ADM-05 : modération admin (masquer une annonce).
 * PostgreSQL ne supporte pas ALTER COLUMN SET pour les enums — on modifie
 * la contrainte CHECK directement.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Supprimer l'ancienne contrainte CHECK
        DB::statement('ALTER TABLE stocks DROP CONSTRAINT IF EXISTS stocks_statut_check');

        // Recréer avec 'masque' inclus
        DB::statement("ALTER TABLE stocks ADD CONSTRAINT stocks_statut_check CHECK (statut IN ('disponible', 'reserve', 'epuise', 'expire', 'masque'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stocks DROP CONSTRAINT IF EXISTS stocks_statut_check');
        DB::statement("ALTER TABLE stocks ADD CONSTRAINT stocks_statut_check CHECK (statut IN ('disponible', 'reserve', 'epuise', 'expire'))");
    }
};