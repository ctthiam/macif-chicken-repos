<?php

namespace App\Http\Requests\Eleveur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request : Modification d'une annonce de stock (STK-03)
 * PUT /api/eleveur/stocks/{id}
 *
 * Tous les champs sont optionnels — PATCH-like.
 * On ne peut pas modifier un stock épuisé ou expiré.
 *
 * Fichier : app/Http/Requests/Eleveur/UpdateStockRequest.php
 */
class UpdateStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre'               => ['sometimes', 'string', 'min:3', 'max:191'],
            'description'         => ['sometimes', 'string', 'min:10', 'max:2000'],
            'quantite_disponible' => ['sometimes', 'integer', 'min:1'],
            'poids_moyen_kg'      => ['sometimes', 'numeric', 'min:0.1', 'max:99.99'],
            'prix_par_kg'         => ['sometimes', 'numeric', 'min:1'],
            'prix_par_unite'      => ['sometimes', 'nullable', 'numeric', 'min:1'],
            'mode_vente'          => ['sometimes', 'string', 'in:vivant,abattu,les_deux'],
            'statut'              => ['sometimes', 'string', 'in:disponible,reserve,epuise,expire'],
            'date_disponibilite'  => ['sometimes', 'date', 'after_or_equal:today'],
            'date_peremption_stock' => ['sometimes', 'nullable', 'date', 'after:date_disponibilite'],

            // Photos : ajout de nouvelles photos
            'photos'   => ['sometimes', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],

            // URLs des photos à supprimer
            'photos_a_supprimer'   => ['sometimes', 'array'],
            'photos_a_supprimer.*' => ['string', 'url'],
        ];
    }

    public function messages(): array
    {
        return [
            'statut.in'                         => 'Statut invalide.',
            'mode_vente.in'                     => 'Le mode de vente doit être : vivant, abattu ou les_deux.',
            'date_disponibilite.after_or_equal'  => 'La date de disponibilité ne peut pas être dans le passé.',
            'date_peremption_stock.after'         => 'La date de péremption doit être après la date de disponibilité.',
            'photos.max'                          => 'Vous ne pouvez pas uploader plus de 5 photos.',
            'photos.*.image'                      => 'Chaque fichier doit être une image.',
            'photos.*.mimes'                      => 'Les images doivent être au format JPEG, JPG, PNG ou WebP.',
            'photos.*.max'                        => 'Chaque image ne doit pas dépasser 2 Mo.',
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