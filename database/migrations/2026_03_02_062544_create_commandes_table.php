<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acheteur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('eleveur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('cascade');
            $table->integer('quantite');
            $table->decimal('poids_total', 8, 2);
            $table->decimal('montant_total', 12, 0);
            $table->decimal('commission_plateforme', 12, 0); // 7% du montant_total
            $table->decimal('montant_eleveur', 12, 0);       // 93% du montant_total
            $table->enum('mode_paiement', ['wave', 'orange_money', 'free_money'])->nullable();
            $table->enum('statut_paiement', ['en_attente', 'paye', 'libere', 'rembourse'])->default('en_attente');
            $table->enum('statut_commande', ['confirmee', 'en_preparation', 'en_livraison', 'livree', 'annulee', 'litige'])->default('confirmee');
            $table->text('adresse_livraison');
            $table->date('date_livraison_souhaitee')->nullable();
            $table->text('note_livraison')->nullable();
            $table->timestamp('escrow_libere_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};