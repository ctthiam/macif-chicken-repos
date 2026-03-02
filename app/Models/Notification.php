<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id', 'titre', 'message', 'type', 'is_read', 'data',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'data'    => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}