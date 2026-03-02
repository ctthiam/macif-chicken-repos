<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Abonnement extends Model
{
    // Prix par plan (FCFA)
    const PRIX = [
        'starter' => 5000,
        'pro'     => 15000,
        'premium' => 30000,
    ];

    // Limite de stocks par plan
    const STOCK_LIMIT = [
        'starter' => 3,
        'pro'     => 10,
        'premium' => null, // illimité
    ];

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