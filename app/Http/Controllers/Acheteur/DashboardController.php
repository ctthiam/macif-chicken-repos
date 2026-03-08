<?php

namespace App\Http\Controllers\Acheteur;

use App\Http\Controllers\Controller;
use App\Http\Resources\EleveurPublicResource;
use App\Models\Commande;
use App\Models\Favori;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Dashboard acheteur
 *
 * Fichier : app/Http/Controllers/Acheteur/DashboardController.php
 *
 * Route : GET /api/acheteur/dashboard
 *
 * Retourne en une seule requête :
 *   DSH-06 : Historique commandes (COUNT par statut)
 *   DSH-07 : Dépenses du mois (SUM montant_total commandes confirmées)
 *   DSH-08 : Éleveurs favoris (liste depuis table favoris)
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $acheteur   = $request->user();
        $acheteurId = $acheteur->id;
        $now        = now();

        // ── DSH-06 : Historique commandes ────────────────────────

        $commandesParStatut = Commande::where('acheteur_id', $acheteurId)
            ->selectRaw('statut_commande, COUNT(*) as total')
            ->groupBy('statut_commande')
            ->pluck('total', 'statut_commande')
            ->toArray();

        $commandesParStatut = array_merge([
            'confirmee'      => 0,
            'en_preparation' => 0,
            'en_livraison'   => 0,
            'livree'         => 0,
            'annulee'        => 0,
            'litige'         => 0,
        ], $commandesParStatut);

        $totalCommandes = array_sum($commandesParStatut);

        // 5 dernières commandes pour aperçu rapide
        $dernieresCommandes = Commande::where('acheteur_id', $acheteurId)
            ->with(['stock:id,titre,photos', 'eleveur:id,name'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id'              => $c->id,
                'statut_commande' => $c->statut_commande,
                'montant_total'   => (int) $c->montant_total,
                'quantite'        => $c->quantite,
                'stock_titre'     => $c->stock?->titre,
                'stock_photo'     => $c->stock?->photos[0] ?? null,
                'eleveur_nom'     => $c->eleveur?->name,
                'created_at'      => $c->created_at?->toISOString(),
            ]);

        // ── DSH-07 : Dépenses du mois ────────────────────────────

        $depensesMois = Commande::where('acheteur_id', $acheteurId)
            ->whereNotIn('statut_commande', ['annulee'])
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->sum('montant_total');

        $depensesAnnee = Commande::where('acheteur_id', $acheteurId)
            ->whereNotIn('statut_commande', ['annulee'])
            ->whereYear('created_at', $now->year)
            ->sum('montant_total');

        // ── DSH-08 : Éleveurs favoris ────────────────────────────

        $favoris = Favori::where('user_id', $acheteurId)
            ->with(['eleveur.eleveurProfile'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($f) => [
                'eleveur_id'    => $f->eleveur_id,
                'nom'           => $f->eleveur?->name,
                'nom_poulailler'=> $f->eleveur?->eleveurProfile?->nom_poulailler,
                'localisation'  => $f->eleveur?->eleveurProfile?->localisation,
                'note_moyenne'  => $f->eleveur?->eleveurProfile?->note_moyenne,
                'is_certified'  => $f->eleveur?->eleveurProfile?->is_certified ?? false,
            ]);

        // ── Réponse consolidée ───────────────────────────────────

        return response()->json([
            'success' => true,
            'message' => 'Dashboard acheteur récupéré avec succès.',
            'data'    => [

                // DSH-06
                'commandes' => [
                    'total'       => $totalCommandes,
                    'par_statut'  => $commandesParStatut,
                    'dernieres'   => $dernieresCommandes,
                ],

                // DSH-07
                'depenses' => [
                    'mois_en_cours' => (int) $depensesMois,
                    'annee'         => (int) $depensesAnnee,
                    'mois'          => $now->month,
                    'annee_label'   => $now->year,
                ],

                // DSH-08
                'favoris' => [
                    'total'   => $favoris->count(),
                    'eleveurs'=> $favoris,
                ],
            ],
        ], 200);
    }
}