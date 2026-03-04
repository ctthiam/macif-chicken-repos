<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fichier : app/Models/Avis.php
 */
class Avis extends Model
{
    use HasFactory;

    protected $fillable = [
        'commande_id',
        'auteur_id',
        'cible_id',
        'note',
        'commentaire',
        'reply',
        'is_reported',
    ];

    protected $casts = [
        'is_reported' => 'boolean',
        'note'        => 'integer',
    ];

    public function auteur()
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function cible()
    {
        return $this->belongsTo(User::class, 'cible_id');
    }

    public function commande()
    {
        return $this->belongsTo(Commande::class);
    }
}