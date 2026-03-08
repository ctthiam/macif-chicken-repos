<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Litige;
use App\Services\PaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Gestion des litiges (admin)
 *
 * Fichier : app/Http/Controllers/Admin/LitigeController.php
 *
 * Routes :
 *   GET /api/admin/litiges                       — Liste tous les litiges
 *   ADM-07 : PUT /api/admin/litiges/{id}/resoudre — Résoudre un litige
 */
class LitigeController extends Controller
{
    public function __construct(
        private readonly PaiementService $paiementService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // GET /api/admin/litiges
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $query = Litige::with([
            'commande:id,montant_total,statut_commande,eleveur_id,acheteur_id',
            'commande.eleveur:id,name',
            'demandeur:id,name,email',
        ])->orderByDesc('created_at');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $litiges = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => collect($litiges->items())->map(fn($l) => [
                'id'          => $l->id,
                'statut'      => $l->statut,
                'raison'      => $l->raison,
                'created_at'  => $l->created_at?->toISOString(),
                'acheteur'    => $l->demandeur ? [
                    'id'    => $l->demandeur->id,
                    'name'  => $l->demandeur->name,
                    'email' => $l->demandeur->email,
                ] : null,
                'eleveur'     => $l->commande?->eleveur ? [
                    'id'   => $l->commande->eleveur->id,
                    'name' => $l->commande->eleveur->name,
                ] : null,
                'commande'    => $l->commande ? [
                    'id'      => $l->commande->id,
                    'montant' => (int) $l->commande->montant_total,
                    'statut'  => $l->commande->statut_commande,
                ] : null,
            ]),
            'meta'    => [
                'current_page' => $litiges->currentPage(),
                'last_page'    => $litiges->lastPage(),
                'total'        => $litiges->total(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // ADM-07 — Résoudre un litige
    // PUT /api/admin/litiges/{id}/resoudre
    // ══════════════════════════════════════════════════════════════

    /**
     * Décisions possibles :
     *   decision=remboursement → statut = 'resolu_remboursement' + refund acheteur
     *   decision=liberation    → statut = 'resolu_liberation'    + libère fonds éleveur
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse
     */
    public function resoudre(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'decision'   => ['required', 'in:remboursement,liberation'],
            'resolution' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $litige = Litige::find($id);

        if (!$litige) {
            return response()->json(['success' => false, 'message' => 'Litige introuvable.'], 404);
        }

        if ($litige->statut !== 'ouvert' && $litige->statut !== 'en_cours') {
            return response()->json([
                'success' => false,
                'message' => 'Ce litige est déjà résolu.',
            ], 422);
        }

        $commande = $litige->commande;
        $decision = $request->decision;

        if ($decision === 'remboursement') {
            // Rembourser l'acheteur via méthode existante
            $this->paiementService->rembourserPaiement($commande);
            $nouveauStatut = 'resolu_remboursement';
        } else {
            // Libérer les fonds à l'éleveur : marquer comme libéré
            $commande->update(['statut_paiement' => 'libere']);
            $nouveauStatut = 'resolu_liberation';
        }

        $litige->update([
            'statut'     => $nouveauStatut,
            'resolution' => $request->resolution,
            'resolu_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Litige résolu avec succès.',
            'data'    => [
                'id'         => $litige->id,
                'statut'     => $litige->statut,
                'resolution' => $litige->resolution,
                'resolu_at'  => $litige->resolu_at->toISOString(),
            ],
        ], 200);
    }
}