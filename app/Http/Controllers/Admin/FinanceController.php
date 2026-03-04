<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Contrôleur : Finances admin
 *
 * Fichier : app/Http/Controllers/Admin/FinanceController.php
 *
 * Routes :
 *   GET /api/admin/finances            — Résumé financier JSON
 *   GET /api/admin/finances/export     — DSH-10 : Export CSV
 */
class FinanceController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // GET /api/admin/finances — Résumé JSON
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $now = now();

        $revenusTotal = Commande::where('statut_commande', 'livree')->sum('commission_plateforme');
        $revenusMois  = Commande::where('statut_commande', 'livree')
            ->whereYear('updated_at', $now->year)
            ->whereMonth('updated_at', $now->month)
            ->sum('commission_plateforme');
        $volumeTotal  = Commande::where('statut_commande', 'livree')->sum('montant_total');

        return response()->json([
            'success' => true,
            'data'    => [
                'commission_total' => (int) $revenusTotal,
                'commission_mois'  => (int) $revenusMois,
                'volume_total'     => (int) $volumeTotal,
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // DSH-10 — Export rapport financier CSV
    // GET /api/admin/finances/export
    // ══════════════════════════════════════════════════════════════

    /**
     * Exporte les commandes livrées en CSV.
     *
     * Paramètres optionnels :
     *   ?mois=3&annee=2026    — filtre par mois/année
     *   ?annee=2026           — filtre par année seulement
     *
     * @param  Request $request
     * @return Response  text/csv
     */
    public function export(Request $request): Response
    {
        $request->validate([
            'mois'  => ['nullable', 'integer', 'between:1,12'],
            'annee' => ['nullable', 'integer', 'min:2024'],
        ]);

        $query = Commande::with(['stock:id,titre', 'eleveur:id,name', 'acheteur:id,name'])
            ->where('statut_commande', 'livree')
            ->orderBy('updated_at');

        if ($request->filled('annee')) {
            $query->whereYear('updated_at', $request->annee);
        }
        if ($request->filled('mois')) {
            $query->whereMonth('updated_at', $request->mois);
        }

        $commandes = $query->get();

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'ID Commande',
            'Date livraison',
            'Éleveur',
            'Acheteur',
            'Stock',
            'Quantité',
            'Montant total (FCFA)',
            'Commission plateforme (FCFA)',
            'Montant éleveur (FCFA)',
            'Mode paiement',
            'Statut paiement',
        ]);

        foreach ($commandes as $commande) {
            fputcsv($handle, [
                $commande->id,
                $commande->updated_at?->toDateString(),
                $commande->eleveur?->name,
                $commande->acheteur?->name,
                $commande->stock?->titre,
                $commande->quantite,
                $commande->montant_total,
                $commande->commission_plateforme,
                $commande->montant_eleveur,
                $commande->mode_paiement,
                $commande->statut_paiement,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $mois     = $request->filled('mois')  ? sprintf('-%02d', $request->mois)  : '';
        $annee    = $request->filled('annee') ? '-' . $request->annee : '';
        $filename = "macif-finances{$annee}{$mois}.csv";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}