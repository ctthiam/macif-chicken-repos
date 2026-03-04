<?php

namespace App\Http\Requests\Eleveur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request : Création d'une annonce de stock (STK-01 + STK-02)
 * POST /api/eleveur/stocks
 *
 * Fichier : app/Http/Requests/Eleveur/CreateStockRequest.php
 */
class CreateStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Infos obligatoires ──────────────────────────────
            'titre'               => ['required', 'string', 'min:3', 'max:191'],
            'description'         => ['required', 'string', 'min:10', 'max:2000'],
            'quantite_disponible' => ['required', 'integer', 'min:1'],
            'poids_moyen_kg'      => ['required', 'numeric', 'min:0.1', 'max:99.99'],
            'prix_par_kg'         => ['required', 'numeric', 'min:1'],
            'mode_vente'          => ['required', 'string', 'in:vivant,abattu,les_deux'],
            'date_disponibilite'  => ['required', 'date', 'after_or_equal:today'],

            // ── Infos optionnelles ──────────────────────────────
            'prix_par_unite'        => ['nullable', 'numeric', 'min:1'],
            'date_peremption_stock' => ['nullable', 'date', 'after:date_disponibilite'],

            // ── STK-02 : Photos (max 5, 2Mo chacune) ───────────
            'photos'   => ['sometimes', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'titre.required'               => 'Le titre de l\'annonce est obligatoire.',
            'titre.min'                    => 'Le titre doit contenir au moins 3 caractères.',
            'description.required'         => 'La description est obligatoire.',
            'description.min'              => 'La description doit contenir au moins 10 caractères.',
            'quantite_disponible.required' => 'La quantité est obligatoire.',
            'quantite_disponible.min'      => 'La quantité doit être d\'au moins 1.',
            'poids_moyen_kg.required'      => 'Le poids moyen est obligatoire.',
            'poids_moyen_kg.min'           => 'Le poids moyen doit être d\'au moins 0.1 kg.',
            'prix_par_kg.required'         => 'Le prix par kg est obligatoire.',
            'prix_par_kg.min'              => 'Le prix par kg doit être supérieur à 0.',
            'mode_vente.required'          => 'Le mode de vente est obligatoire.',
            'mode_vente.in'                => 'Le mode de vente doit être : vivant, abattu ou les_deux.',
            'date_disponibilite.required'  => 'La date de disponibilité est obligatoire.',
            'date_disponibilite.after_or_equal' => 'La date de disponibilité ne peut pas être dans le passé.',
            'date_peremption_stock.after'  => 'La date de péremption doit être après la date de disponibilité.',
            'photos.max'                   => 'Vous ne pouvez pas uploader plus de 5 photos.',
            'photos.*.image'               => 'Chaque fichier doit être une image.',
            'photos.*.mimes'               => 'Les images doivent être au format JPEG, JPG, PNG ou WebP.',
            'photos.*.max'                 => 'Chaque image ne doit pas dépasser 2 Mo.',
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