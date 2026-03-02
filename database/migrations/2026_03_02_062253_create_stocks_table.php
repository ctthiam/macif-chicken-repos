<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleveur_id')->constrained('users')->onDelete('cascade');
            $table->string('titre');
            $table->text('description');
            $table->integer('quantite_disponible');
            $table->decimal('poids_moyen_kg', 4, 2);
            $table->decimal('prix_par_kg', 10, 0);
            $table->decimal('prix_par_unite', 10, 0)->nullable();
            $table->enum('mode_vente', ['vivant', 'abattu', 'les_deux']);
            $table->date('date_disponibilite');
            $table->date('date_peremption_stock')->nullable();
            $table->json('photos')->nullable();
            $table->enum('statut', ['disponible', 'reserve', 'epuise', 'expire'])->default('disponible');
            $table->integer('vues')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};