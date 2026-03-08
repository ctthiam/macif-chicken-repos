<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Litige;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Contrôleur : Dashboard admin
 *
 * Fichier : app/Http/Controllers/Admin/DashboardController.php
 *
 * Routes :
 *   DSH-09 : GET /api/admin/dashboard          — KPIs globaux
 *   DSH-10 : GET /api/admin/finances/export    — Export CSV
 */
class DashboardController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // DSH-09 — KPIs globaux temps réel
    // GET /api/admin/dashboard
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $now = now();

        // ── Utilisateurs ─────────────────────────────────────────

        $totalUsers    = User::where('role', '!=', 'admin')->count();
        $totalEleveurs = User::where('role', 'eleveur')->where('is_verified', true)->count();
        $totalAcheteurs= User::where('role', 'acheteur')->where('is_verified', true)->count();
        $nouveauxJour  = User::whereDate('created_at', $now->toDateString())->count();

        // ── Commandes ────────────────────────────────────────────

        $commandesJour = Commande::whereDate('created_at', $now->toDateString())->count();
        $commandesMois = Commande::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $commandesParStatut = Commande::selectRaw('statut_commande, COUNT(*) as total')
            ->groupBy('statut_commande')
            ->pluck('total', 'statut_commande')
            ->toArray();

        // ── Revenus plateforme ───────────────────────────────────

        $revenusMois = Commande::where('statut_commande', 'livree')
            ->whereYear('updated_at', $now->year)
            ->whereMonth('updated_at', $now->month)
            ->sum('commission_plateforme');

        $revenusTotal = Commande::where('statut_commande', 'livree')
            ->sum('commission_plateforme');

        $volumeMois = Commande::where('statut_commande', 'livree')
            ->whereYear('updated_at', $now->year)
            ->whereMonth('updated_at', $now->month)
            ->sum('montant_total');

        // ── Litiges ──────────────────────────────────────────────

        $litigesOuverts = Litige::where('statut', 'ouvert')->count();
        $litigesTotal   = Litige::count();

        // ── Réponse ──────────────────────────────────────────────

        // ── Données récentes pour le dashboard ──────────────────
        $derniersUtilisateurs = User::where('role', '!=', 'admin')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role,
                'is_verified'=> $u->is_verified,
                'created_at' => $u->created_at?->toISOString(),
            ]);

        $derniersLitiges = Litige::with(['commande:id', 'acheteur:id,name', 'eleveur:id,name'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($l) => [
                'id'         => $l->id,
                'motif'      => $l->motif,
                'statut'     => $l->statut,
                'created_at' => $l->created_at?->toISOString(),
                'acheteur'   => $l->acheteur ? ['name' => $l->acheteur->name] : null,
                'eleveur'    => $l->eleveur  ? ['name' => $l->eleveur->name]  : null,
            ]);

        $activiteRecente = Commande::with(['acheteur:id,name', 'stock:id,titre'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'statut_commande'=> $c->statut_commande,
                'montant_total'  => (int) $c->montant_total,
                'created_at'     => $c->created_at?->toISOString(),
                'acheteur'       => $c->acheteur ? ['name' => $c->acheteur->name] : null,
                'stock'          => $c->stock    ? ['titre' => $c->stock->titre]  : null,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'KPIs admin récupérés avec succès.',
            'data'    => [
                'users' => [
                    'total'          => $totalUsers,
                    'eleveurs'       => $totalEleveurs,
                    'acheteurs'      => $totalAcheteurs,
                    'nouveaux_jour'  => $nouveauxJour,
                ],
                'commandes' => [
                    'aujourd_hui'    => $commandesJour,
                    'ce_mois'        => $commandesMois,
                    'par_statut'     => $commandesParStatut,
                ],
                'revenus' => [
                    'commission_mois'  => (int) $revenusMois,
                    'commission_total' => (int) $revenusTotal,
                    'volume_mois'      => (int) $volumeMois,
                ],
                'litiges' => [
                    'ouverts' => $litigesOuverts,
                    'total'   => $litigesTotal,
                ],
                'genere_le'              => $now->toISOString(),
                'derniers_utilisateurs'  => $derniersUtilisateurs,
                'derniers_litiges'       => $derniersLitiges,
                'activite_recente'       => $activiteRecente,
            ],
        ], 200);
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
     * @return Response  application/csv
     */
    public function exportCsv(Request $request): Response
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

        // Générer le CSV avec fputcsv natif PHP
        $handle = fopen('php://temp', 'r+');

        // En-têtes CSV
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

        // Lignes
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

        $mois   = $request->filled('mois')  ? sprintf('-%02d', $request->mois) : '';
        $annee  = $request->filled('annee') ? '-' . $request->annee : '';
        $filename = "macif-finances{$annee}{$mois}.csv";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}