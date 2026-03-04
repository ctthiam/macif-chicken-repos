<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EleveurProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur Admin : Gestion des utilisateurs
 *
 * Fichier : app/Http/Controllers/Admin/UserController.php
 *
 * Routes couvertes :
 *   PRO-07 : PUT /api/admin/users/{id}/certifier     — Badge éleveur certifié
 *   ADMIN  : GET /api/admin/users                    — Liste utilisateurs (stub)
 *   ADMIN  : PUT /api/admin/users/{id}/toggle-status — Activer/suspendre (stub)
 */
class UserController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // PRO-07 — Badge Éleveur Certifié
    // PUT /api/admin/users/{id}/certifier
    // ══════════════════════════════════════════════════════════════

    /**
     * Bascule le statut de certification d'un éleveur.
     * Action réservée à l'admin (middleware role.admin sur la route).
     *
     * - Vérifie que l'utilisateur existe et est bien un éleveur
     * - Bascule is_certified : false → true, true → false
     * - Retourne le profil mis à jour
     *
     * @param  int $id  ID de l'utilisateur éleveur
     * @return JsonResponse  200 | 404 | 422
     */
    public function certifier(int $id): JsonResponse
    {
        // ── 1. Trouver l'éleveur ────────────────────────────────────
        $user = User::where('id', $id)
            ->where('role', 'eleveur')
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Éleveur introuvable.',
                'errors'  => [],
            ], 404);
        }

        // ── 2. Vérifier que le profil éleveur existe ─────────────────
        $profile = $user->eleveurProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Cet éleveur n\'a pas encore créé son profil poulailler.',
                'errors'  => [],
            ], 422);
        }

        // ── 3. Basculer le statut de certification ───────────────────
        $wasCertified  = $profile->is_certified;
        $profile->update(['is_certified' => !$wasCertified]);

        $user->load('eleveurProfile');

        $message = $profile->fresh()->is_certified
            ? "L'éleveur {$user->name} a été certifié avec succès."
            : "La certification de l'éleveur {$user->name} a été retirée.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => new EleveurProfileResource($user),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — Liste des utilisateurs
    // GET /api/admin/users
    // ══════════════════════════════════════════════════════════════

    /**
     * @param  Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // TODO — sprint Admin
        return response()->json(['success' => false, 'message' => 'Non implémenté.', 'data' => null], 501);
    }

    // ══════════════════════════════════════════════════════════════
    // ADMIN — Activer / Suspendre un compte
    // PUT /api/admin/users/{id}/toggle-status
    // ══════════════════════════════════════════════════════════════

    /**
     * @param  int $id
     * @return JsonResponse
     */
    public function toggleStatus(int $id): JsonResponse
    {
        // TODO — sprint Admin
        return response()->json(['success' => false, 'message' => 'Non implémenté.', 'data' => null], 501);
    }
}