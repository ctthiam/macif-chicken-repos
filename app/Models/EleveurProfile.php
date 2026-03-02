<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EleveurProfile extends Model
{
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
    ];

    protected $casts = [
        'is_certified' => 'boolean',
        'note_moyenne' => 'decimal:1',
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}