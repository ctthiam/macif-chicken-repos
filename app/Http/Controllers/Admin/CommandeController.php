<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Vue commandes admin
 *
 * Fichier : app/Http/Controllers/Admin/CommandeController.php
 *
 * Routes :
 *   ADM-06 : GET /api/admin/commandes — Toutes les commandes avec filtres
 */
class CommandeController extends Controller
{
    /**
     * Filtres disponibles :
     *   ?statut_commande=confirmee|en_preparation|...
     *   ?eleveur_id=X
     *   ?acheteur_id=X
     *   ?from=2026-01-01
     *   ?to=2026-03-31
     *   ?per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Commande::with([
            'eleveur:id,name,email',
            'acheteur:id,name,email',
            'stock:id,titre',
        ])->orderByDesc('created_at');

        if ($request->filled('statut_commande')) {
            $query->where('statut_commande', $request->statut_commande);
        }

        if ($request->filled('eleveur_id')) {
            $query->where('eleveur_id', $request->eleveur_id);
        }

        if ($request->filled('acheteur_id')) {
            $query->where('acheteur_id', $request->acheteur_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $commandes = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Commandes récupérées avec succès.',
            'data'    => $commandes->map(fn ($c) => [
                'id'              => $c->id,
                'reference'       => 'CMD-' . str_pad($c->id, 5, '0', STR_PAD_LEFT),
                'statut_commande' => $c->statut_commande,
                'statut_paiement' => $c->statut_paiement,
                'montant_total'   => (int) $c->montant_total,
                'commission'      => (int) $c->commission_plateforme,
                'quantite'        => $c->quantite,
                'eleveur'         => ['name' => $c->eleveur?->name ?? '—'],
                'acheteur'        => ['name' => $c->acheteur?->name ?? '—'],
                'stock'           => ['titre' => $c->stock?->titre ?? '—'],
                'created_at'      => $c->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $commandes->currentPage(),
                'last_page'    => $commandes->lastPage(),
                'total'        => $commandes->total(),
            ],
        ], 200);
    }
}