<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EleveurPublicResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Profil public éleveur
 *
 * Fichier : app/Http/Controllers/Public/EleveurPublicController.php
 *
 * Routes couvertes :
 *   PRO-06 : GET /api/eleveurs/{id}/public
 *
 * Accessible sans authentification — route publique.
 */
class EleveurPublicController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // PRO-06 — Page publique d'un éleveur
    // GET /api/eleveurs/{id}/public
    // ══════════════════════════════════════════════════════════════

    /**
     * Retourne le profil public d'un éleveur avec :
     *   - Ses infos publiques (nom, ville, avatar, certified)
     *   - Son profil poulailler (description, localisation, photos)
     *   - Ses stocks actuellement disponibles (statut = 'disponible')
     *   - Ses 10 derniers avis non signalés
     *
     * Incrémente le compteur de vues sur chaque stock affiché.
     * Ne nécessite pas d'authentification.
     *
     * @param  int $id  ID de l'éleveur
     * @return JsonResponse  200 | 404
     */
    // ══════════════════════════════════════════════════════════════
    // Liste publique des éleveurs actifs
    // GET /api/eleveurs
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'eleveur')
            ->where('is_active', true)
            ->where('is_verified', true)
            ->with('eleveurProfile')
            ->whereHas('eleveurProfile')
            ->withCount(['stocks as stocks_count' => fn($q) => $q->where('statut', 'disponible')]);

        // Recherche par nom/localisation
        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                  ->orWhereHas('eleveurProfile', fn($p) => $p->where('nom_poulailler', 'ILIKE', $search)
                    ->orWhere('localisation', 'ILIKE', $search));
            });
        }

        // Tri
        match ($request->get('sort', 'note_desc')) {
            'note_desc' => $query->join('eleveur_profiles', 'users.id', '=', 'eleveur_profiles.user_id')
                                 ->orderByDesc('eleveur_profiles.note_moyenne')
                                 ->select('users.*'),
            default     => $query->latest('users.created_at'),
        };

        $eleveurs = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'message' => 'Éleveurs récupérés avec succès.',
            'data'    => $eleveurs->map(fn($e) => [
                'id'             => $e->id,
                'name'           => $e->name,
                'avatar'         => $e->avatar,
                'ville'          => $e->ville,
                'nom_poulailler' => $e->eleveurProfile?->nom_poulailler,
                'localisation'   => $e->eleveurProfile?->localisation,
                'note_moyenne'   => $e->eleveurProfile?->note_moyenne,
                'nombre_avis'    => $e->eleveurProfile?->nombre_avis,
                'is_certified'   => $e->eleveurProfile?->is_certified ?? false,
                'stocks_count'   => $e->stocks()->where('statut', 'disponible')->count(),
            ]),
            'meta' => [
                'current_page' => $eleveurs->currentPage(),
                'last_page'    => $eleveurs->lastPage(),
                'total'        => $eleveurs->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        // ── 1. Trouver l'éleveur actif ──────────────────────────────
        $eleveur = User::where('id', $id)
            ->where('role', 'eleveur')
            ->where('is_active', true)
            ->first();

        if (!$eleveur) {
            return response()->json([
                'success' => false,
                'message' => 'Éleveur introuvable.',
                'errors'  => [],
            ], 404);
        }

        // ── 2. Charger le profil poulailler ─────────────────────────
        $eleveur->load('eleveurProfile');

        // ── 3. Charger les stocks disponibles ───────────────────────
        $eleveur->load(['stocks' => function ($query) {
            $query->where('statut', 'disponible')
                ->orderByDesc('created_at');
        }]);

        // ── 4. Charger les 10 derniers avis non signalés ────────────
        $eleveur->load(['avisRecus' => function ($query) {
            $query->where('is_reported', false)
                ->with('auteur:id,name,avatar')
                ->orderByDesc('created_at')
                ->limit(10);
        }]);

        // ── 5. Incrémenter les vues sur les stocks chargés ──────────
        // On fait un update groupé pour éviter N requêtes
        if ($eleveur->stocks->isNotEmpty()) {
            $eleveur->stocks()
                ->where('statut', 'disponible')
                ->increment('vues');
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil éleveur récupéré avec succès.',
            'data'    => new EleveurPublicResource($eleveur),
        ], 200);
    }
}