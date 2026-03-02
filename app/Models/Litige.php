<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Litige extends Model
{
    protected $fillable = [
        'commande_id', 'demandeur_id', 'raison',
        'statut', 'resolution', 'resolu_at',
    ];

    protected $casts = [
        'resolu_at' => 'datetime',
    ];

    public function commande()   { return $this->belongsTo(Commande::class); }
    public function demandeur()  { return $this->belongsTo(User::class, 'demandeur_id'); }
}