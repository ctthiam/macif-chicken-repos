<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource : Formate un stock pour les réponses API.
 *
 * Fichier : app/Http/Resources/StockResource.php
 */
class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'eleveur_id'            => $this->eleveur_id,
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
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}