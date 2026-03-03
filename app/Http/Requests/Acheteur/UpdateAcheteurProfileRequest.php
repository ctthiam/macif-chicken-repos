<?php

namespace App\Http\Requests\Acheteur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request : Modification du profil acheteur (PRO-05)
 * PUT /api/acheteur/profile
 *
 * Fichier : app/Http/Requests/Acheteur/UpdateAcheteurProfileRequest.php
 */
class UpdateAcheteurProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Champs user ──────────────────────────────────────
            'ville'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],

            // ── Champs acheteur_profiles ─────────────────────────
            'type' => [
                'sometimes',
                'string',
                'in:restaurant,cantine,hotel,traiteur,particulier',
            ],
            'nom_etablissement' => ['sometimes', 'nullable', 'string', 'max:191'],

            // NINEA : identifiant fiscal sénégalais (9 chiffres + 3 lettres)
            'ninea' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^[0-9]{9}[A-Z]{2}[0-9]{1}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'        => 'Le type doit être : restaurant, cantine, hotel, traiteur ou particulier.',
            'ninea.regex'    => 'Le NINEA est invalide (format : 9 chiffres + 2 lettres + 1 chiffre, ex: 123456789AB1).',
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