<?php

namespace App\Http\Controllers\Eleveur;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur : Dashboard éleveur
 *
 * Fichier : app/Http/Controllers/Eleveur/DashboardController.php
 *
 * Route : GET /api/eleveur/dashboard
 *
 * Retourne en une seule requête :
 *   DSH-01 : CA mensuel et annuel (SUM commandes livrées)
 *   DSH-02 : Commandes en cours (COUNT par statut_commande)
 *   DSH-03 : Stocks actifs restants (SUM quantite_disponible)
 *   DSH-04 : Graphique ventes 30 derniers jours (data JSON)
 *   DSH-05 : Note moyenne et nombre d'avis
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $eleveur   = $request->user();
        $eleveurId = $eleveur->id;
        $now       = now();

        // ── DSH-01 : Chiffre d'affaires ──────────────────────────

        $caAnnuel = Commande::where('eleveur_id', $eleveurId)
            ->where('statut_commande', 'livree')
            ->whereYear('updated_at', $now->year)
            ->sum('montant_eleveur');

        $caMensuel = Commande::where('eleveur_id', $eleveurId)
            ->where('statut_commande', 'livree')
            ->whereYear('updated_at', $now->year)
            ->whereMonth('updated_at', $now->month)
            ->sum('montant_eleveur');

        // ── DSH-02 : Commandes en cours ──────────────────────────

        $commandesEnCours = Commande::where('eleveur_id', $eleveurId)
            ->whereIn('statut_commande', ['confirmee', 'en_preparation', 'en_livraison'])
            ->selectRaw('statut_commande, COUNT(*) as total')
            ->groupBy('statut_commande')
            ->pluck('total', 'statut_commande')
            ->toArray();

        $commandesEnCours = array_merge([
            'confirmee'      => 0,
            'en_preparation' => 0,
            'en_livraison'   => 0,
        ], $commandesEnCours);

        $totalEnCours = array_sum($commandesEnCours);

        // ── DSH-03 : Stocks actifs ───────────────────────────────

        $stocksActifs = Stock::where('eleveur_id', $eleveurId)
            ->whereIn('statut', ['disponible', 'reserve'])
            ->selectRaw('COUNT(*) as total_annonces, SUM(quantite_disponible) as total_quantite')
            ->first();

        // ── DSH-04 : Graphique ventes 30 jours ───────────────────

        $ventes30j = Commande::where('eleveur_id', $eleveurId)
            ->where('statut_commande', 'livree')
            ->where('updated_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->selectRaw('DATE(updated_at) as date, SUM(montant_eleveur) as ca, COUNT(*) as nb_commandes')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date'          => $row->date,
                'ca'            => (int) $row->ca,
                'nb_commandes'  => (int) $row->nb_commandes,
            ]);

        // ── DSH-05 : Note moyenne et avis ────────────────────────

        $profile = $eleveur->eleveurProfile;
        $noteMoyenne  = $profile ? (float) $profile->note_moyenne : 0.0;
        $nombreAvis   = $profile ? (int)   $profile->nombre_avis  : 0;

        // ── Réponse consolidée ───────────────────────────────────

        return response()->json([
            'success' => true,
            'message' => 'Dashboard éleveur récupéré avec succès.',
            'data'    => [

                // DSH-01
                'chiffre_affaires' => [
                    'mensuel' => (int) $caMensuel,
                    'annuel'  => (int) $caAnnuel,
                    'mois'    => $now->month,
                    'annee'   => $now->year,
                ],

                // DSH-02
                'commandes_en_cours' => [
                    'total'      => $totalEnCours,
                    'par_statut' => $commandesEnCours,
                ],

                // DSH-03
                'stocks' => [
                    'total_annonces'  => (int) ($stocksActifs->total_annonces  ?? 0),
                    'total_quantite'  => (int) ($stocksActifs->total_quantite  ?? 0),
                ],

                // DSH-04
                'ventes_30j' => $ventes30j,

                // DSH-05
                'avis' => [
                    'note_moyenne' => $noteMoyenne,
                    'nombre_avis'  => $nombreAvis,
                ],
            ],
        ], 200);
    }
}