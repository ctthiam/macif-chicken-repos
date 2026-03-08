<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\EleveurProfile;

/**
 * Fichier : app/Models/Avis.php
 *
 * Colonnes :
 *   commande_id (unique), auteur_id, cible_id,
 *   note (tinyint 1-5), commentaire, reply, is_reported
 */
class Avis extends Model
{
    use HasFactory;

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

    // ══════════════════════════════════════════════════════════════
    // AVI-04 — Recalcul note moyenne après création/suppression
    // ══════════════════════════════════════════════════════════════

    /**
     * Recalcule et sauvegarde note_moyenne + nombre_avis
     * dans eleveur_profiles pour un éleveur donné.
     *
     * @param  int $eleveurId  ID de l'éleveur (cible_id)
     */
    public static function recalculeNoteMoyenne(int $eleveurId): void
    {
        $stats = static::where('cible_id', $eleveurId)
            ->where('is_reported', false)
            ->selectRaw('AVG(note) as moyenne, COUNT(*) as total')
            ->first();

        // updateOrCreate garantit que même si l'éleveur n'a pas encore de profil,
        // la note_moyenne est bien sauvegardée (nom_poulailler requis à la création)
        EleveurProfile::updateOrCreate(
            ['user_id' => $eleveurId],
            [
                'nom_poulailler' => EleveurProfile::where('user_id', $eleveurId)->value('nom_poulailler') ?? 'Mon poulailler',
                'note_moyenne'   => round((float) ($stats->moyenne ?? 0), 1),
                'nombre_avis'    => (int) ($stats->total ?? 0),
            ]
        );
    }
}