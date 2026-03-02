<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Avis extends Model
{
    protected $fillable = [
        'commande_id', 'auteur_id', 'cible_id',
        'note', 'commentaire', 'reply', 'is_reported',
    ];

    protected $casts = [
        'note'        => 'integer',
        'is_reported' => 'boolean',
    ];

    public function commande() { return $this->belongsTo(Commande::class); }
    public function auteur()   { return $this->belongsTo(User::class, 'auteur_id'); }
    public function cible()    { return $this->belongsTo(User::class, 'cible_id'); }
}