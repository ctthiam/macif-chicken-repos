<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EleveurProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Gestion utilisateurs (admin)
 *
 * Fichier : app/Http/Controllers/Admin/UserController.php
 *
 * Routes :
 *   ADM-02 : GET /api/admin/users                        — Liste avec filtres
 *   ADM-03 : PUT /api/admin/users/{id}/toggle-status     — Suspendre/réactiver
 *   ADM-04 : PUT /api/admin/users/{id}/certifier         — Certifier éleveur
 */
class UserController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // ADM-02 — Liste et recherche utilisateurs
    // GET /api/admin/users
    // ══════════════════════════════════════════════════════════════

    /**
     * Filtres disponibles :
     *   ?role=eleveur|acheteur|admin
     *   ?is_active=1|0
     *   ?ville=Dakar
     *   ?search=nom_ou_email
     *   ?per_page=20 (défaut 20)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('eleveurProfile:user_id,nom_poulailler,is_certified,note_moyenne')
            ->orderByDesc('created_at');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->is_active);
        }

        if ($request->filled('ville')) {
            $query->where('ville', 'ilike', '%' . $request->ville . '%');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        $users = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Utilisateurs récupérés avec succès.',
            'data'    => $users->map(fn ($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'phone'        => $u->phone,
                'role'         => $u->role,
                'ville'        => $u->ville,
                'is_active'    => $u->is_active,
                'is_verified'  => $u->is_verified,
                'is_certified' => $u->eleveurProfile?->is_certified ?? false,
                'created_at'   => $u->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'total'        => $users->total(),
            ],
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-03 — Suspendre / réactiver un compte
    // PUT /api/admin/users/{id}/toggle-status
    // ══════════════════════════════════════════════════════════════

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        // Protéger les admins
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier le statut d\'un administrateur.',
            ], 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        $action = $user->is_active ? 'réactivé' : 'suspendu';

        return response()->json([
            'success'   => true,
            'message'   => "Compte {$action} avec succès.",
            'data'      => [
                'id'        => $user->id,
                'is_active' => $user->is_active,
            ],
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-04 — Certifier un éleveur
    // PUT /api/admin/users/{id}/certifier
    // ══════════════════════════════════════════════════════════════

    public function certifier(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        if ($user->role !== 'eleveur') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les éleveurs peuvent être certifiés.',
            ], 422);
        }

        $profile = $user->eleveurProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil éleveur introuvable.',
            ], 404);
        }

        // Toggle certification
        $profile->update(['is_certified' => !$profile->is_certified]);

        $action = $profile->is_certified ? 'certifié' : 'décertifié';

        return response()->json([
            'success' => true,
            'message' => "Éleveur {$action} avec succès.",
            'data'    => [
                'user_id'      => $user->id,
                'is_certified' => $profile->is_certified,
            ],
        ], 200);
    }
}