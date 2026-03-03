<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource : Formate le profil acheteur complet pour les réponses API.
 *
 * Fichier : app/Http/Resources/AcheteurProfileResource.php
 */
class AcheteurProfileResource extends JsonResource
{
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

            // ── Données profil acheteur ──────────────────────────
            'acheteur_profile' => $this->when(
                $this->relationLoaded('acheteurProfile') && $this->acheteurProfile,
                fn () => [
                    'id'                => $this->acheteurProfile->id,
                    'type'              => $this->acheteurProfile->type,
                    'nom_etablissement' => $this->acheteurProfile->nom_etablissement,
                    'ninea'             => $this->acheteurProfile->ninea,
                ]
            ),
        ];
    }
}