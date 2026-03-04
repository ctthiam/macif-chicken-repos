<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource : Paiement / Transaction
 *
 * Fichier : app/Http/Resources/PaiementResource.php
 */
class PaiementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'reference_transaction' => $this->reference_transaction,
            'montant'               => $this->montant,
            'methode'               => $this->methode,
            'statut'                => $this->statut,
            'created_at'            => $this->created_at?->toISOString(),

            'commande' => $this->when(
                $this->relationLoaded('commande') && $this->commande,
                fn () => [
                    'id'              => $this->commande->id,
                    'statut_commande' => $this->commande->statut_commande,
                    'montant_total'   => $this->commande->montant_total,
                    'montant_eleveur' => $this->commande->montant_eleveur,
                    'quantite'        => $this->commande->quantite,
                    'stock'           => $this->when(
                        $this->commande->relationLoaded('stock') && $this->commande->stock,
                        fn () => [
                            'id'    => $this->commande->stock->id,
                            'titre' => $this->commande->stock->titre,
                        ]
                    ),
                    'acheteur' => $this->when(
                        $this->commande->relationLoaded('acheteur') && $this->commande->acheteur,
                        fn () => [
                            'id'   => $this->commande->acheteur->id,
                            'name' => $this->commande->acheteur->name,
                        ]
                    ),
                ]
            ),
        ];
    }
}