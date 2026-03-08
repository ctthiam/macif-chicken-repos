<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource : Stock public — visible sans authentification.
 * Inclut les infos de l'éleveur nécessaires à l'affichage en liste.
 *
 * Fichier : app/Http/Resources/StockPublicResource.php
 */
class StockPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'titre'                 => $this->titre,
            'description'           => $this->description,
            'quantite_disponible'   => $this->quantite_disponible,
            'poids_moyen_kg'        => $this->poids_moyen_kg,
            'prix_par_kg'           => $this->prix_par_kg,
            'prix_par_unite'        => $this->prix_par_unite,
            'mode_vente'            => $this->mode_vente,
            'date_disponibilite'    => $this->date_disponibilite?->toDateString(),
            'date_peremption_stock' => $this->date_peremption_stock?->toDateString(),
            'photos'                => $this->photos ?? [],
            'statut'                => $this->statut,
            'vues'                  => $this->vues,
            'created_at'            => $this->created_at?->toISOString(),

            // ── Avis du stock ───────────────────────────────────
            'avis' => $this->when(
                $this->relationLoaded('avis'),
                fn () => $this->avis->map(fn ($a) => [
                    'id'          => $a->id,
                    'note'        => $a->note,
                    'commentaire' => $a->commentaire,
                    'created_at'  => $a->created_at?->toISOString(),
                    'auteur'      => $a->auteur ? ['name' => $a->auteur->name] : null,
                    'reply'       => $a->reply,
                ])->values()->all()
            ),

            // ── Éleveur (infos publiques seulement) ─────────────
            'eleveur' => $this->when(
                $this->relationLoaded('eleveur') && $this->eleveur,
                fn () => [
                    'id'     => $this->eleveur->id,
                    'name'   => $this->eleveur->name,
                    'avatar' => $this->eleveur->avatar,
                    'ville'  => $this->eleveur->ville,

                    'profil' => $this->when(
                        $this->eleveur->relationLoaded('eleveurProfile') && $this->eleveur->eleveurProfile,
                        fn () => [
                            'nom_poulailler' => $this->eleveur->eleveurProfile->nom_poulailler,
                            'is_certified'   => $this->eleveur->eleveurProfile->is_certified,
                            'note_moyenne'   => $this->eleveur->eleveurProfile->note_moyenne,
                            'nombre_avis'    => $this->eleveur->eleveurProfile->nombre_avis,
                            'latitude'       => $this->eleveur->eleveurProfile->latitude,
                            'longitude'      => $this->eleveur->eleveurProfile->longitude,
                        ]
                    ),
                ]
            ),
        ];
    }
}