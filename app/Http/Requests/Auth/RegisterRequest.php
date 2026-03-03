<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request : Validation de l'inscription (AUTH-01 / AUTH-02)
 *
 * Champs communs : name, email, password, phone, role
 * Champs éleveur : nom_poulailler (requis si role=eleveur)
 * Champs acheteur : type, nom_etablissement, ninea (requis si role=acheteur)
 */
class RegisterRequest extends FormRequest
{
    /**
     * Tous les utilisateurs authentifiés ou non peuvent s'inscrire.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation selon le rôle.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'email'    => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone'    => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/', 'unique:users,phone'],
            'role'     => ['required', 'in:eleveur,acheteur'],
            'ville'    => ['nullable', 'string', 'max:100'],
            'adresse'  => ['nullable', 'string', 'max:255'],
        ];

        // Champs spécifiques éleveur
        if ($this->input('role') === 'eleveur') {
            $rules['nom_poulailler'] = ['required', 'string', 'min:2', 'max:191'];
            $rules['description']    = ['nullable', 'string', 'max:1000'];
            $rules['localisation']   = ['nullable', 'string', 'max:255'];
        }

        // Champs spécifiques acheteur
        if ($this->input('role') === 'acheteur') {
            $rules['type']               = ['required', 'in:restaurant,cantine,hotel,traiteur,particulier'];
            $rules['nom_etablissement']  = ['nullable', 'string', 'max:191'];
            $rules['ninea']              = ['nullable', 'string', 'max:50'];
        }

        return $rules;
    }

    /**
     * Messages d'erreur en français.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'            => 'Le nom complet est obligatoire.',
            'name.min'                 => 'Le nom doit contenir au moins 2 caractères.',
            'email.required'           => 'L\'adresse email est obligatoire.',
            'email.email'              => 'L\'adresse email est invalide.',
            'email.unique'             => 'Cette adresse email est déjà utilisée.',
            'password.required'        => 'Le mot de passe est obligatoire.',
            'password.min'             => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'       => 'La confirmation du mot de passe ne correspond pas.',
            'phone.required'           => 'Le numéro de téléphone est obligatoire.',
            'phone.regex'              => 'Le numéro de téléphone est invalide (ex: +221771234567).',
            'phone.unique'             => 'Ce numéro de téléphone est déjà utilisé.',
            'role.required'            => 'Le rôle est obligatoire.',
            'role.in'                  => 'Le rôle doit être "eleveur" ou "acheteur".',
            'nom_poulailler.required'  => 'Le nom du poulailler est obligatoire pour un éleveur.',
            'type.required'            => 'Le type d\'établissement est obligatoire pour un acheteur.',
            'type.in'                  => 'Le type doit être : restaurant, cantine, hotel, traiteur ou particulier.',
        ];
    }

    /**
     * Retourne une réponse JSON formatée en cas d'erreur de validation.
     * Format imposé : { success, message, errors }
     */
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