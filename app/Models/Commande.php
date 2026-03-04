<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fichier : app/Models/Commande.php
 */
class Commande extends Model
{
    use HasFactory;

    protected $fillable = [
        'acheteur_id',
        'eleveur_id',
        'stock_id',
        'quantite',
        'poids_total',
        'montant_total',
        'commission_plateforme',
        'montant_eleveur',
        'mode_paiement',
        'statut_paiement',
        'statut_commande',
        'adresse_livraison',
        'date_livraison_souhaitee',
        'note_livraison',
        'escrow_libere_at',
    ];

    protected $casts = [
        'montant_total'           => 'decimal:0',
        'commission_plateforme'   => 'decimal:0',
        'montant_eleveur'         => 'decimal:0',
        'poids_total'             => 'decimal:2',
        'date_livraison_souhaitee'=> 'date',
        'escrow_libere_at'        => 'datetime',
    ];

    public function acheteur()
    {
        return $this->belongsTo(User::class, 'acheteur_id');
    }

    public function eleveur()
    {
        return $this->belongsTo(User::class, 'eleveur_id');
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }

    public function avis()
    {
        return $this->hasOne(Avis::class);
    }

    public function litige()
    {
        return $this->hasOne(Litige::class);
    }
}