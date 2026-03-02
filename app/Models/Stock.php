<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'eleveur_id',
        'titre',
        'description',
        'quantite_disponible',
        'poids_moyen_kg',
        'prix_par_kg',
        'prix_par_unite',
        'mode_vente',
        'date_disponibilite',
        'date_peremption_stock',
        'photos',
        'statut',
        'vues',
    ];

    protected $casts = [
        'photos'                => 'array',
        'date_disponibilite'    => 'date',
        'date_peremption_stock' => 'date',
        'poids_moyen_kg'        => 'decimal:2',
        'prix_par_kg'           => 'decimal:0',
        'prix_par_unite'        => 'decimal:0',
    ];

    public function eleveur()
    {
        return $this->belongsTo(User::class, 'eleveur_id');
    }

    public function commandes()
    {
        return $this->hasMany(Commande::class, 'stock_id');
    }
}