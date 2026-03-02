<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleveur_id')->constrained('users')->onDelete('cascade');
            $table->enum('plan', ['starter', 'pro', 'premium']);
            $table->decimal('prix_mensuel', 10, 0);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', ['actif', 'expire', 'suspendu'])->default('actif');
            $table->enum('methode_paiement', ['wave', 'orange_money', 'free_money'])->nullable();
            $table->string('reference_paiement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements');
    }
};