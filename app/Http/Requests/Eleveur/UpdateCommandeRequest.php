<?php

namespace App\Http\Requests\Eleveur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request : Mise à jour statut commande par l'éleveur (CMD-03, CMD-05)
 * PUT /api/eleveur/commandes/{id}
 *
 * L'éleveur envoie l'action souhaitée :
 *   - 'confirmer'      → confirmee → en_preparation  (CMD-03)
 *   - 'en_livraison'   → en_preparation → en_livraison (CMD-05)
 *   - 'livree'         → en_livraison → livree          (CMD-05)
 *
 * Fichier : app/Http/Requests/Eleveur/UpdateCommandeRequest.php
 */
class UpdateCommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:confirmer,en_livraison,livree'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'L\'action est obligatoire.',
            'action.in'       => 'Action invalide. Valeurs acceptées : confirmer, en_livraison, livree.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Erreur de validation.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}