<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fichier : app/Models/Abonnement.php
 */
class Abonnement extends Model
{
    use HasFactory;

    // Prix par plan (FCFA/mois)
    const PRIX = [
        'starter' => 5000,
        'pro'     => 15000,
        'premium' => 30000,
    ];

    // Limite de stocks actifs par plan (null = illimité)
    const STOCK_LIMIT = [
        'starter' => 3,
        'pro'     => 10,
        'premium' => null,
    ];

    // Durée en jours (1 mois = 30 jours)
    const DUREE_JOURS = 30;

    protected $fillable = [
        'eleveur_id', 'plan', 'prix_mensuel',
        'date_debut', 'date_fin', 'statut',
        'methode_paiement', 'reference_paiement',
    ];

    protected $casts = [
        'prix_mensuel' => 'decimal:0',
        'date_debut'   => 'date',
        'date_fin'     => 'date',
    ];

    public function eleveur()
    {
        return $this->belongsTo(User::class, 'eleveur_id');
    }

    public function isActif(): bool
    {
        return $this->statut === 'actif' && $this->date_fin->isFuture();
    }

    public function getStockLimit(): ?int
    {
        return self::STOCK_LIMIT[$this->plan] ?? null;
    }
}