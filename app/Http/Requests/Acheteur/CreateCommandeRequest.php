<?php

namespace App\Http\Requests\Acheteur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request : Passer une commande (CMD-01)
 * POST /api/acheteur/commandes
 *
 * Fichier : app/Http/Requests/Acheteur/CreateCommandeRequest.php
 */
class CreateCommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_id'                 => ['required', 'integer', 'exists:stocks,id'],
            'quantite'                 => ['required', 'integer', 'min:1'],
            'adresse_livraison'        => ['required', 'string', 'min:3', 'max:500'],
            'mode_paiement'            => ['required', 'string', 'in:wave,orange_money,free_money'],
            'date_livraison_souhaitee' => ['nullable', 'date', 'after_or_equal:today'],
            'note_livraison'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'stock_id.required'          => 'Le stock est obligatoire.',
            'stock_id.exists'            => 'Ce stock n\'existe pas.',
            'quantite.required'          => 'La quantité est obligatoire.',
            'quantite.min'               => 'La quantité doit être d\'au moins 1.',
            'adresse_livraison.required' => 'L\'adresse de livraison est obligatoire.',
            'adresse_livraison.min'      => 'L\'adresse de livraison doit contenir au moins 3 caractères.',
            'mode_paiement.required'     => 'Le mode de paiement est obligatoire.',
            'mode_paiement.in'           => 'Le mode de paiement doit être : wave, orange_money ou free_money.',
            'date_livraison_souhaitee.after_or_equal' => 'La date de livraison ne peut pas être dans le passé.',
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