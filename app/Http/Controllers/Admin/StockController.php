<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Modération des annonces (admin)
 *
 * Fichier : app/Http/Controllers/Admin/StockController.php
 *
 * Routes :
 *   GET /api/admin/stocks                     — Liste toutes les annonces
 *   ADM-05 : PUT /api/admin/stocks/{id}/moderer — Masquer ou supprimer
 */
class StockController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // GET /api/admin/stocks — Liste toutes les annonces
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $query = Stock::with(['eleveur:id,name,email', 'eleveur.eleveurProfile:user_id,is_certified'])
            ->orderByDesc('created_at');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('eleveur_id')) {
            $query->where('eleveur_id', $request->eleveur_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhereHas('eleveur', fn($e) => $e->where('name', 'ilike', "%{$search}%"));
            });
        }

        $stocks = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => collect($stocks->items())->map(fn($s) => [
                'id'          => $s->id,
                'titre'       => $s->titre,
                'statut'      => $s->statut,
                'prix_par_kg' => $s->prix_par_kg,
                'quantite_disponible' => $s->quantite_disponible,
                'created_at'  => $s->created_at?->toISOString(),
                'eleveur'     => $s->eleveur ? [
                    'id'    => $s->eleveur->id,
                    'name'  => $s->eleveur->name,
                    'email' => $s->eleveur->email,
                ] : null,
            ]),
            'meta'    => [
                'current_page' => $stocks->currentPage(),
                'last_page'    => $stocks->lastPage(),
                'total'        => $stocks->total(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-05 — Modérer une annonce
    // PUT /api/admin/stocks/{id}/moderer
    // ══════════════════════════════════════════════════════════════

    /**
     * Actions disponibles :
     *   action=masquer   → statut = 'masque' (soft hide)
     *   action=supprimer → suppression définitive
     *   action=restaurer → statut = 'disponible'
     */
    public function moderer(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'in:masquer,supprimer,restaurer'],
            'raison' => ['nullable', 'string', 'max:500'],
        ]);

        $stock = Stock::find($id);

        if (!$stock) {
            return response()->json(['success' => false, 'message' => 'Annonce introuvable.'], 404);
        }

        $action = $request->action;

        if ($action === 'supprimer') {
            $stock->delete();
            return response()->json([
                'success' => true,
                'message' => 'Annonce supprimée définitivement.',
            ], 200);
        }

        $nouveauStatut = $action === 'masquer' ? 'expire' : 'disponible';
        $stock->update(['statut' => $nouveauStatut]);

        $label = $action === 'masquer' ? 'masquée' : 'restaurée';

        return response()->json([
            'success' => true,
            'message' => "Annonce {$label} avec succès.",
            'data'    => [
                'id'     => $stock->id,
                'statut' => $stock->statut,
            ],
        ], 200);
    }
}