<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource : Profil public d'un éleveur.
 * Visible sans authentification — ne retourne PAS les données sensibles
 * (email, phone, adresse exacte, is_active, tokens...).
 *
 * Contient : infos éleveur + stocks actifs + avis reçus
 *
 * Fichier : app/Http/Resources/EleveurPublicResource.php
 */
class EleveurPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Infos publiques éleveur ──────────────────────────
            'id'          => $this->id,
            'name'        => $this->name,
            'avatar'      => $this->avatar,
            'ville'       => $this->ville,
            'created_at'  => $this->created_at?->toDateString(),

            // ── Profil poulailler ────────────────────────────────
            'eleveur_profile' => $this->when(
                $this->relationLoaded('eleveurProfile') && $this->eleveurProfile,
                fn () => [
                    'nom_poulailler' => $this->eleveurProfile->nom_poulailler,
                    'description'    => $this->eleveurProfile->description,
                    'localisation'   => $this->eleveurProfile->localisation,
                    'latitude'       => $this->eleveurProfile->latitude,
                    'longitude'      => $this->eleveurProfile->longitude,
                    'is_certified'   => $this->eleveurProfile->is_certified,
                    'note_moyenne'   => $this->eleveurProfile->note_moyenne,
                    'nombre_avis'    => $this->eleveurProfile->nombre_avis,
                    'photos'         => $this->eleveurProfile->photos ?? [],
                ]
            ),

            // ── Stocks actifs (disponibles à l'achat) ───────────
            'stocks' => $this->when(
                $this->relationLoaded('stocks'),
                fn () => $this->stocks->map(fn ($stock) => [
                    'id'                  => $stock->id,
                    'titre'               => $stock->titre,
                    'description'         => $stock->description,
                    'quantite_disponible' => $stock->quantite_disponible,
                    'poids_moyen_kg'      => $stock->poids_moyen_kg,
                    'prix_par_kg'         => $stock->prix_par_kg,
                    'prix_par_unite'      => $stock->prix_par_unite,
                    'mode_vente'          => $stock->mode_vente,
                    'date_disponibilite'  => $stock->date_disponibilite?->toDateString(),
                    'photos'              => $stock->photos ?? [],
                    'vues'                => $stock->vues,
                ])
            ),

            // ── Avis reçus (les 10 derniers) ────────────────────
            'avis' => $this->when(
                $this->relationLoaded('avisRecus'),
                fn () => $this->avisRecus->map(fn ($avis) => [
                    'id'          => $avis->id,
                    'note'        => $avis->note,
                    'commentaire' => $avis->commentaire,
                    'reply'       => $avis->reply,
                    'auteur'      => [
                        'name'   => $avis->auteur?->name,
                        'avatar' => $avis->auteur?->avatar,
                    ],
                    'created_at'  => $avis->created_at?->toDateString(),
                ])
            ),

            // ── Statistiques globales ────────────────────────────
            'stats' => [
                'total_stocks'   => $this->when(
                    $this->relationLoaded('stocks'),
                    fn () => $this->stocks->count()
                ),
                'total_avis'     => $this->when(
                    $this->relationLoaded('avisRecus'),
                    fn () => $this->avisRecus->count()
                ),
                'note_moyenne'   => $this->eleveurProfile?->note_moyenne,
                'nombre_avis'    => $this->eleveurProfile?->nombre_avis,
                'is_certified'   => $this->eleveurProfile?->is_certified ?? false,
                'localisation'   => $this->eleveurProfile?->localisation,
                'nom_poulailler' => $this->eleveurProfile?->nom_poulailler,
            ],
        ];
    }
}