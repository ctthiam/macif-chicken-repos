<?php

namespace App\Http\Requests\Eleveur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request : Modification du profil éleveur (PRO-03)
 * PUT /api/eleveur/profile
 *
 * Champs modifiables :
 *   - nom_poulailler, description, localisation (texte)
 *   - latitude, longitude (coordonnées GPS)
 *   - photos (tableau d'images uploadées — PRO-02)
 *
 * Fichier : app/Http/Requests/Eleveur/UpdateEleveurProfileRequest.php
 */
class UpdateEleveurProfileRequest extends FormRequest
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
        return [
            // ── Infos poulailler ────────────────────────────────
            'nom_poulailler' => ['sometimes', 'string', 'min:2', 'max:191'],
            'description'    => ['sometimes', 'nullable', 'string', 'max:1000'],
            'localisation'   => ['sometimes', 'nullable', 'string', 'max:255'],

            // ── Coordonnées GPS ─────────────────────────────────
            'latitude'  => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],

            // ── Photos poulailler (PRO-02) ───────────────────────
            // Tableau de fichiers images — max 5 photos, 2Mo chacune
            'photos'          => ['sometimes', 'array', 'max:5'],
            'photos.*'        => ['image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],

            // ── Champs user de base (optionnels ici) ─────────────
            'ville'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom_poulailler.min'  => 'Le nom du poulailler doit contenir au moins 2 caractères.',
            'nom_poulailler.max'  => 'Le nom du poulailler ne peut pas dépasser 191 caractères.',
            'latitude.between'    => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.between'   => 'La longitude doit être comprise entre -180 et 180.',
            'photos.max'          => 'Vous ne pouvez pas uploader plus de 5 photos.',
            'photos.*.image'      => 'Chaque fichier doit être une image.',
            'photos.*.mimes'      => 'Les images doivent être au format JPEG, JPG, PNG ou WebP.',
            'photos.*.max'        => 'Chaque image ne doit pas dépasser 2 Mo.',
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