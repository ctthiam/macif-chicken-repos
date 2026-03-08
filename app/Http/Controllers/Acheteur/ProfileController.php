<?php

namespace App\Http\Controllers\Acheteur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Acheteur\UpdateAcheteurProfileRequest;
use App\Http\Resources\AcheteurProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Gestion du profil acheteur
 *
 * Fichier : app/Http/Controllers/Acheteur/ProfileController.php
 *
 * Routes couvertes :
 *   PRO-04 : GET /api/acheteur/profile
 *   PRO-05 : PUT /api/acheteur/profile
 */
class ProfileController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // PRO-04 — Récupérer mon profil acheteur
    // GET /api/acheteur/profile
    // ══════════════════════════════════════════════════════════════

    /**
     * Retourne le profil complet de l'acheteur connecté.
     *
     * @param  Request $request
     * @return JsonResponse  200
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('acheteurProfile');

        return response()->json([
            'success' => true,
            'message' => 'Profil acheteur récupéré avec succès.',
            'data'    => new AcheteurProfileResource($user),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // PRO-05 — Modifier mon profil acheteur
    // PUT /api/acheteur/profile
    // ══════════════════════════════════════════════════════════════

    /**
     * Met à jour le profil de l'acheteur connecté.
     *
     * Champs modifiables sur users : ville, adresse
     * Champs modifiables sur acheteur_profiles : type, nom_etablissement, ninea
     * Tous les champs sont optionnels — seuls les champs présents sont mis à jour.
     *
     * @param  UpdateAcheteurProfileRequest $request
     * @return JsonResponse  200 | 422
     */
    public function update(UpdateAcheteurProfileRequest $request): JsonResponse
    {
        $user    = $request->user();
        $profile = $user->acheteurProfile;

        // ── 1. Champs user ──────────────────────────────────────────
        $userFields = [];
        if ($request->filled('name'))    $userFields['name']    = $request->name;
        if ($request->has('phone'))      $userFields['phone']   = $request->phone;
        if ($request->has('ville'))      $userFields['ville']   = $request->ville;
        if ($request->has('adresse'))    $userFields['adresse'] = $request->adresse;
        if (!empty($userFields)) $user->update($userFields);

        // ── 2. Champs profil acheteur ───────────────────────────────
        $profileFields = [];
        if ($request->filled('type'))               $profileFields['type']              = $request->type;
        if ($request->has('nom_etablissement'))     $profileFields['nom_etablissement'] = $request->nom_etablissement;
        if ($request->has('ninea'))                 $profileFields['ninea']             = $request->ninea;
        if (!empty($profileFields)) $profile->update($profileFields);

        $user->load('acheteurProfile');

        return response()->json([
            'success' => true,
            'message' => 'Profil acheteur mis à jour avec succès.',
            'data'    => new AcheteurProfileResource($user),
        ], 200);
    }
}