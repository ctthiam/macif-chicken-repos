<?php // app/Models/Paiement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
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