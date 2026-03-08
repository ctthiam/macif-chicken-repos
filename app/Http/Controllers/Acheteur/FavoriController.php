<?php

namespace App\Http\Controllers\Acheteur;

use App\Http\Controllers\Controller;
use App\Models\Favori;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Favoris acheteur
 *
 * GET    /api/acheteur/favoris              — Liste des éleveurs favoris
 * POST   /api/acheteur/favoris/{eleveur_id} — Ajouter un favori
 * DELETE /api/acheteur/favoris/{eleveur_id} — Retirer un favori
 */
class FavoriController extends Controller
{
    // ── GET /api/acheteur/favoris ─────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $favoris = Favori::where('user_id', $request->user()->id)
            ->with(['eleveur.eleveurProfile'])
            ->orderByDesc('id')
            ->get()
            ->map(fn ($f) => [
                'eleveur_id'     => $f->eleveur_id,
                'nom'            => $f->eleveur?->name,
                'nom_poulailler' => $f->eleveur?->eleveurProfile?->nom_poulailler,
                'localisation'   => $f->eleveur?->eleveurProfile?->localisation,
                'note_moyenne'   => $f->eleveur?->eleveurProfile?->note_moyenne,
                'is_certified'   => $f->eleveur?->eleveurProfile?->is_certified ?? false,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Favoris récupérés.',
            'data'    => $favoris,
        ]);
    }

    // ── POST /api/acheteur/favoris/{eleveur_id} ───────────────────
    public function store(Request $request, int $eleveur_id): JsonResponse
    {
        // Vérifier que l'éleveur existe
        $eleveur = User::where('id', $eleveur_id)->where('role', 'eleveur')->first();
        if (!$eleveur) {
            return response()->json([
                'success' => false,
                'message' => 'Éleveur introuvable.',
            ], 404);
        }

        // Idempotent — pas d'erreur si déjà en favori
        Favori::firstOrCreate([
            'user_id'    => $request->user()->id,
            'eleveur_id' => $eleveur_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Éleveur ajouté aux favoris.',
        ], 201);
    }

    // ── DELETE /api/acheteur/favoris/{eleveur_id} ─────────────────
    public function destroy(Request $request, int $eleveur_id): JsonResponse
    {
        Favori::where('user_id', $request->user()->id)
            ->where('eleveur_id', $eleveur_id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Éleveur retiré des favoris.',
        ]);
    }
}