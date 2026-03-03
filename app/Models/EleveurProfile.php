<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fichier : app/Models/EleveurProfile.php
 */
class EleveurProfile extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'nom_poulailler',
        'description',
        'localisation',
        'latitude',
        'longitude',
        'is_certified',
        'note_moyenne',
        'nombre_avis',
        'photos',       // PRO-02 : tableau JSON d'URLs
    ];

    protected $casts = [
        'is_certified' => 'boolean',
        'note_moyenne' => 'decimal:1',
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
        'photos'       => 'array',   // JSON → array PHP automatiquement
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}