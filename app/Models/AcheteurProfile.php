<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcheteurProfile extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'nom_etablissement',
        'ninea',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}