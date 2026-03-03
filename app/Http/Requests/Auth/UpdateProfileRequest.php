<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request : Validation de la modification de profil (AUTH-07)
 *
 * Champs modifiables : name, phone, avatar (fichier image)
 * Tous les champs sont optionnels — on met à jour seulement ce qui est envoyé.
 *
 * Fichier : app/Http/Requests/Auth/UpdateProfileRequest.php
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'name'   => ['sometimes', 'string', 'min:2', 'max:100'],

            // Phone unique sauf pour l'utilisateur courant
            'phone'  => [
                'sometimes',
                'string',
                'regex:/^\+?[0-9]{8,15}$/',
                "unique:users,phone,{$userId}",
            ],

            // Avatar : image uniquement, max 2Mo
            'avatar' => [
                'sometimes',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048', // 2Mo en Ko
            ],

            // Champs de localisation optionnels
            'ville'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min'       => 'Le nom doit contenir au moins 2 caractères.',
            'phone.regex'    => 'Le numéro de téléphone est invalide (ex: +221771234567).',
            'phone.unique'   => 'Ce numéro de téléphone est déjà utilisé.',
            'avatar.image'   => 'Le fichier doit être une image.',
            'avatar.mimes'   => 'L\'image doit être au format JPEG, JPG, PNG ou WebP.',
            'avatar.max'     => 'L\'image ne doit pas dépasser 2 Mo.',
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