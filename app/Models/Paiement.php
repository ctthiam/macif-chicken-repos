<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fichier : app/Models/Paiement.php
 */
class Paiement extends Model
{
    use HasFactory;

    protected $fillable = [
        'commande_id', 'user_id', 'montant', 'methode',
        'reference_transaction', 'statut', 'webhook_data',
    ];

    protected $casts = [
        'montant'      => 'decimal:0',
        'webhook_data' => 'array',
    ];

    public function commande() { return $this->belongsTo(Commande::class); }
    public function user()     { return $this->belongsTo(User::class); }
}