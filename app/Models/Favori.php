<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favori extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'eleveur_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()    { return $this->belongsTo(User::class, 'user_id'); }
    public function eleveur() { return $this->belongsTo(User::class, 'eleveur_id'); }
}