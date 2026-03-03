<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fichier : app/Models/AcheteurProfile.php
 */
class AcheteurProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',              // restaurant|cantine|hotel|traiteur|particulier
        'nom_etablissement',
        'ninea',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}