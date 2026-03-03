<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource : Formate les données utilisateur pour toutes les réponses API.
 * Ne jamais retourner le password, token ou champs sensibles.
 */
class UserResource extends JsonResource
{
    /**
     * Transforme l'utilisateur en tableau JSON.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'role'       => $this->role,
            'avatar'     => $this->avatar,
            'adresse'    => $this->adresse,
            'ville'      => $this->ville,
            'is_verified'=> $this->is_verified,
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),

            // Profil éleveur — inclus si chargé (withLoaded)
            'eleveur_profile' => $this->when(
                $this->role === 'eleveur' && $this->relationLoaded('eleveurProfile'),
                fn () => [
                    'nom_poulailler' => $this->eleveurProfile?->nom_poulailler,
                    'description'    => $this->eleveurProfile?->description,
                    'localisation'   => $this->eleveurProfile?->localisation,
                    'latitude'       => $this->eleveurProfile?->latitude,
                    'longitude'      => $this->eleveurProfile?->longitude,
                    'is_certified'   => $this->eleveurProfile?->is_certified,
                    'note_moyenne'   => $this->eleveurProfile?->note_moyenne,
                    'nombre_avis'    => $this->eleveurProfile?->nombre_avis,
                ]
            ),

            // Profil acheteur — inclus si chargé
            'acheteur_profile' => $this->when(
                $this->role === 'acheteur' && $this->relationLoaded('acheteurProfile'),
                fn () => [
                    'type'               => $this->acheteurProfile?->type,
                    'nom_etablissement'  => $this->acheteurProfile?->nom_etablissement,
                    'ninea'              => $this->acheteurProfile?->ninea,
                ]
            ),
        ];
    }
}