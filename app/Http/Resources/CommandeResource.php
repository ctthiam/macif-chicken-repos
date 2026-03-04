<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource : Commande complète pour les réponses API.
 *
 * Fichier : app/Http/Resources/CommandeResource.php
 */
class CommandeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'statut_commande'          => $this->statut_commande,
            'statut_paiement'          => $this->statut_paiement,
            'quantite'                 => $this->quantite,
            'poids_total'              => $this->poids_total,
            'montant_total'            => $this->montant_total,
            'commission_plateforme'    => $this->commission_plateforme,
            'montant_eleveur'          => $this->montant_eleveur,
            'mode_paiement'            => $this->mode_paiement,
            'adresse_livraison'        => $this->adresse_livraison,
            'date_livraison_souhaitee' => $this->date_livraison_souhaitee?->toDateString(),
            'note_livraison'           => $this->note_livraison,
            'escrow_libere_at'         => $this->escrow_libere_at?->toISOString(),
            'created_at'               => $this->created_at?->toISOString(),
            'updated_at'               => $this->updated_at?->toISOString(),

            // ── Stock commandé ───────────────────────────────────
            'stock' => $this->when(
                $this->relationLoaded('stock') && $this->stock,
                fn () => [
                    'id'             => $this->stock->id,
                    'titre'          => $this->stock->titre,
                    'mode_vente'     => $this->stock->mode_vente,
                    'prix_par_kg'    => $this->stock->prix_par_kg,
                    'poids_moyen_kg' => $this->stock->poids_moyen_kg,
                    'photos'         => $this->stock->photos ?? [],
                ]
            ),

            // ── Éleveur ──────────────────────────────────────────
            'eleveur' => $this->when(
                $this->relationLoaded('eleveur') && $this->eleveur,
                fn () => [
                    'id'     => $this->eleveur->id,
                    'name'   => $this->eleveur->name,
                    'avatar' => $this->eleveur->avatar,
                    'phone'  => $this->eleveur->phone,
                    'ville'  => $this->eleveur->ville,
                ]
            ),

            // ── Acheteur ─────────────────────────────────────────
            'acheteur' => $this->when(
                $this->relationLoaded('acheteur') && $this->acheteur,
                fn () => [
                    'id'     => $this->acheteur->id,
                    'name'   => $this->acheteur->name,
                    'avatar' => $this->acheteur->avatar,
                    'phone'  => $this->acheteur->phone,
                    'ville'  => $this->acheteur->ville,
                ]
            ),
        ];
    }
}