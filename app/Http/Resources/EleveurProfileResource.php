<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource : Formate le profil éleveur complet pour les réponses API.
 * Inclut les données user + eleveur_profile dans un seul objet.
 *
 * Fichier : app/Http/Resources/EleveurProfileResource.php
 */
class EleveurProfileResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // ── Données utilisateur ──────────────────────────────
            'id'          => $this->id,
            'name'        => $this->name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'role'        => $this->role,
            'avatar'      => $this->avatar,
            'ville'       => $this->ville,
            'adresse'     => $this->adresse,
            'is_verified' => $this->is_verified,
            'is_active'   => $this->is_active,
            'created_at'  => $this->created_at?->toISOString(),

            // ── Données profil éleveur ───────────────────────────
            'eleveur_profile' => $this->when(
                $this->relationLoaded('eleveurProfile') && $this->eleveurProfile,
                fn () => [
                    'id'             => $this->eleveurProfile->id,
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

            // ── Abonnement actif (si chargé) ─────────────────────
            'abonnement' => $this->when(
                $this->relationLoaded('abonnementActif') && $this->abonnementActif,
                fn () => [
                    'plan'        => $this->abonnementActif->plan,
                    'statut'      => $this->abonnementActif->statut,
                    'date_fin'    => $this->abonnementActif->date_fin?->toDateString(),
                    'stock_limit' => $this->abonnementActif->getStockLimit(),
                ]
            ),
        ];
    }
}