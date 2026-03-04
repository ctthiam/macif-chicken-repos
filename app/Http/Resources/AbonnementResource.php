<?php

namespace App\Http\Resources;

use App\Models\Abonnement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fichier : app/Http/Resources/AbonnementResource.php
 */
class AbonnementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $joursRestants = $this->date_fin
            ? max(0, (int) now()->diffInDays($this->date_fin, false))
            : null;

        return [
            'id'                 => $this->id,
            'plan'               => $this->plan,
            'prix_mensuel'       => (int) $this->prix_mensuel,
            'date_debut'         => $this->date_debut?->toDateString(),
            'date_fin'           => $this->date_fin?->toDateString(),
            'statut'             => $this->statut,
            'methode_paiement'   => $this->methode_paiement,
            'reference_paiement' => $this->reference_paiement,
            'jours_restants'     => $joursRestants,
            'est_actif'          => $this->isActif(),
            'stock_limit'        => $this->getStockLimit(),
            'created_at'         => $this->created_at?->toISOString(),

            // Infos plan
            'plan_details' => [
                'stocks_max' => Abonnement::STOCK_LIMIT[$this->plan] ?? 'illimité',
                'prix'       => Abonnement::PRIX[$this->plan],
            ],
        ];
    }
}