<?php

namespace App\Http\Controllers\Eleveur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Eleveur\UpdateEleveurProfileRequest;
use App\Http\Resources\EleveurProfileResource;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Gestion du profil éleveur
 *
 * Fichier : app/Http/Controllers/Eleveur/ProfileController.php
 *
 * Routes couvertes :
 *   PRO-01 : GET  /api/eleveur/profile
 *   PRO-02 : inclus dans PRO-03 (upload photos poulailler)
 *   PRO-03 : PUT  /api/eleveur/profile
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly StorageService $storageService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // PRO-01 — Récupérer mon profil éleveur
    // GET /api/eleveur/profile
    // ══════════════════════════════════════════════════════════════

    /**
     * Retourne le profil complet de l'éleveur connecté.
     * Charge eleveurProfile + abonnementActif pour afficher
     * les limites de stocks et le statut de certification.
     *
     * @param  Request $request
     * @return JsonResponse  200
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()
            ->load('eleveurProfile')
            ->load('abonnementActif');

        return response()->json([
            'success' => true,
            'message' => 'Profil éleveur récupéré avec succès.',
            'data'    => new EleveurProfileResource($user),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // PRO-03 — Modifier mon profil éleveur
    // PUT /api/eleveur/profile
    // ══════════════════════════════════════════════════════════════

    /**
     * Met à jour le profil de l'éleveur connecté.
     *
     * Champs modifiables sur users : ville, adresse
     * Champs modifiables sur eleveur_profiles :
     *   nom_poulailler, description, localisation, latitude, longitude
     *
     * PRO-02 inclus : si des photos sont envoyées, elles sont uploadées
     * sur R2 et les URLs sont ajoutées au tableau photos existant.
     * Les photos existantes ne sont PAS supprimées (ajout seulement).
     * Pour supprimer une photo, envoyer photos_a_supprimer[] (URLs).
     *
     * @param  UpdateEleveurProfileRequest $request
     * @return JsonResponse  200 | 422
     */
    public function update(UpdateEleveurProfileRequest $request): JsonResponse
    {
        $user    = $request->user();
        $profile = $user->eleveurProfile;

        // ── 1. Mise à jour des champs user ──────────────────────────
        $userFields = [];
        if ($request->has('ville'))   $userFields['ville']   = $request->ville;
        if ($request->has('adresse')) $userFields['adresse'] = $request->adresse;
        if (!empty($userFields)) $user->update($userFields);

        // ── 2. Mise à jour des champs profil éleveur ────────────────
        $profileFields = [];
        if ($request->filled('nom_poulailler')) $profileFields['nom_poulailler'] = $request->nom_poulailler;
        if ($request->has('description'))       $profileFields['description']    = $request->description;
        if ($request->has('localisation'))      $profileFields['localisation']   = $request->localisation;
        if ($request->has('latitude'))          $profileFields['latitude']       = $request->latitude;
        if ($request->has('longitude'))         $profileFields['longitude']      = $request->longitude;

        // ── 3. PRO-02 : Upload photos poulailler ────────────────────
        if ($request->hasFile('photos')) {
            $existingPhotos = $profile->photos ?? [];
            $newPhotos      = [];

            foreach ($request->file('photos') as $photo) {
                $newPhotos[] = $this->storageService->uploadStockPhoto($photo, $user->id);
            }

            // Ajouter les nouvelles photos aux existantes (max 5 total)
            $allPhotos = array_values(array_unique(array_merge($existingPhotos, $newPhotos)));

            // Garder seulement les 5 dernières si dépassement
            $profileFields['photos'] = array_slice($allPhotos, -5);
        }

        // ── 4. Suppression de photos spécifiques (optionnel) ────────
        // Si l'éleveur envoie photos_a_supprimer[] avec des URLs à retirer
        if ($request->has('photos_a_supprimer') && is_array($request->photos_a_supprimer)) {
            $currentPhotos = $profileFields['photos'] ?? ($profile->photos ?? []);
            $toRemove      = $request->photos_a_supprimer;

            foreach ($toRemove as $url) {
                $this->storageService->deleteByUrl($url);
            }

            $profileFields['photos'] = array_values(
                array_filter($currentPhotos, fn($url) => !in_array($url, $toRemove))
            );
        }

        if (!empty($profileFields)) $profile->update($profileFields);

        // ── 5. Recharger et retourner ────────────────────────────────
        $user->load('eleveurProfile')->load('abonnementActif');

        return response()->json([
            'success' => true,
            'message' => 'Profil éleveur mis à jour avec succès.',
            'data'    => new EleveurProfileResource($user),
        ], 200);
    }
}