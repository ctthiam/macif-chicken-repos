<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fichier : app/Models/Setting.php
 *
 * Table : settings (cle, valeur, description)
 */
class Setting extends Model
{
    protected $fillable = ['cle', 'valeur', 'description'];

    protected $primaryKey = 'id';

    /**
     * Récupère une valeur par clé.
     */
    public static function get(string $cle, mixed $default = null): mixed
    {
        $setting = static::where('cle', $cle)->first();
        return $setting ? $setting->valeur : $default;
    }

    /**
     * Met à jour ou crée une valeur.
     */
    public static function set(string $cle, mixed $valeur): void
    {
        static::updateOrCreate(
            ['cle' => $cle],
            ['valeur' => (string) $valeur]
        );
    }
}