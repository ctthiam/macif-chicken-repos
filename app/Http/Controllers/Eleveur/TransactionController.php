<?php

namespace App\Http\Controllers\Eleveur;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementResource;
use App\Models\Commande;
use App\Models\Paiement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Contrôleur : Transactions éleveur
 *
 * Fichier : app/Http/Controllers/Eleveur/TransactionController.php
 *
 * Routes :
 *   PAY-07 : GET /api/eleveur/transactions              — Historique transactions
 *   PAY-06 : GET /api/eleveur/transactions/{id}/recu   — Reçu PDF téléchargeable
 */
class TransactionController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // PAY-07 — Historique transactions éleveur
    // GET /api/eleveur/transactions
    // ══════════════════════════════════════════════════════════════

    /**
     * Liste paginée des transactions liées aux commandes de l'éleveur connecté.
     * Filtre optionnel : ?statut=initie|confirme|rembourse
     *
     * @param  Request $request
     * @return JsonResponse  200
     */
    public function index(Request $request): JsonResponse
    {
        $eleveur = $request->user();

        $query = Paiement::query()
            ->whereHas('commande', fn ($q) => $q->where('eleveur_id', $eleveur->id))
            ->with(['commande' => fn ($q) => $q->with(['stock', 'acheteur'])])
            ->orderByDesc('created_at');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $transactions = $query->paginate(12);

        // ── Totaux pour le dashboard ──────────────────────────────
        $totalLibere  = Paiement::whereHas('commande', fn ($q) => $q->where('eleveur_id', $eleveur->id))
            ->where('statut', 'confirme')
            ->whereHas('commande', fn ($q) => $q->where('statut_paiement', 'libere'))
            ->sum('montant');

        $totalEnCours = Paiement::whereHas('commande', fn ($q) => $q->where('eleveur_id', $eleveur->id))
            ->where('statut', 'confirme')
            ->whereHas('commande', fn ($q) => $q->where('statut_paiement', 'paye'))
            ->sum('montant');

        return response()->json([
            'success' => true,
            'message' => 'Historique des transactions récupéré avec succès.',
            'data'    => PaiementResource::collection($transactions->items()),
            'meta'    => [
                'current_page'    => $transactions->currentPage(),
                'last_page'       => $transactions->lastPage(),
                'per_page'        => $transactions->perPage(),
                'total'           => $transactions->total(),
                'total_libere'    => (int) $totalLibere,
                'total_en_cours'  => (int) $totalEnCours,
            ],
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // PAY-06 — Reçu PDF
    // GET /api/eleveur/transactions/{id}/recu
    // ══════════════════════════════════════════════════════════════

    /**
     * Génère et retourne un reçu PDF pour une commande.
     * Accessible par l'éleveur propriétaire de la commande.
     *
     * Utilise barryvdh/laravel-dompdf.
     * Template Blade : resources/views/pdf/recu.blade.php
     *
     * @param  Request $request
     * @param  int     $id       ID de la commande (pas du paiement)
     * @return Response|JsonResponse  200 PDF | 403 | 404
     */
    public function recu(Request $request, int $id): Response|JsonResponse
    {
        $eleveur  = $request->user();
        $commande = Commande::with([
            'acheteur',
            'eleveur.eleveurProfile',
            'stock',
        ])->find($id);

        if (!$commande) {
            return response()->json([
                'success' => false,
                'message' => 'Commande introuvable.',
            ], 404);
        }

        if ($commande->eleveur_id !== $eleveur->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à ce reçu.',
            ], 403);
        }

        // Récupérer le paiement associé (peut être null si jamais payé)
        $paiement = Paiement::where('commande_id', $commande->id)
            ->whereIn('statut', ['confirme', 'rembourse'])
            ->latest()
            ->first();

        $pdf = Pdf::loadView('pdf.recu', [
            'commande' => $commande,
            'paiement' => $paiement,
        ])->setPaper('a4', 'portrait');

        $filename = "recu-commande-{$commande->id}.pdf";

        return $pdf->download($filename);
    }
}