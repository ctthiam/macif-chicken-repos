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
        $query = Stock::with('eleveur:id,name,email')
            ->orderByDesc('created_at');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('eleveur_id')) {
            $query->where('eleveur_id', $request->eleveur_id);
        }

        $stocks = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $stocks->items(),
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

        $nouveauStatut = $action === 'masquer' ? 'masque' : 'disponible';
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