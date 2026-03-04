<?php

namespace App\Http\Controllers\Acheteur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Acheteur\CreateCommandeRequest;
use App\Http\Resources\CommandeResource;
use App\Models\Commande;
use App\Models\Stock;
use App\Services\EscrowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur : Commandes côté acheteur
 *
 * Fichier : app/Http/Controllers/Acheteur/CommandeController.php
 *
 * Routes couvertes :
 *   CMD-01 : POST   /api/acheteur/commandes                    — Passer commande
 *   CMD-02 : inclus CMD-01                                      — Calcul montant + 7%
 *   CMD-04 : DELETE /api/acheteur/commandes/{id}               — Annulation
 *   CMD-06 : POST   /api/acheteur/commandes/{id}/confirmer-livraison — Libère escrow
 *   CMD-09 : GET    /api/acheteur/commandes                    — Historique
 *   CMD-11 : GET    /api/acheteur/commandes/{id}               — Détail
 */
class CommandeController extends Controller
{
    public function __construct(
        private readonly EscrowService $escrowService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // CMD-09 — Historique
    // GET /api/acheteur/commandes
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $query = Commande::where('acheteur_id', $request->user()->id)
            ->with(['stock', 'eleveur'])
            ->orderByDesc('created_at');

        if ($request->filled('statut')) {
            $query->where('statut_commande', $request->statut);
        }

        $commandes = $query->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'Historique des commandes récupéré avec succès.',
            'data'    => CommandeResource::collection($commandes->items()),
            'meta'    => [
                'current_page' => $commandes->currentPage(),
                'last_page'    => $commandes->lastPage(),
                'per_page'     => $commandes->perPage(),
                'total'        => $commandes->total(),
            ],
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-01 + CMD-02 — Passer une commande
    // POST /api/acheteur/commandes
    // ══════════════════════════════════════════════════════════════

    public function store(CreateCommandeRequest $request): JsonResponse
    {
        $acheteur = $request->user();

        return DB::transaction(function () use ($request, $acheteur) {

            $stock = Stock::lockForUpdate()->find($request->stock_id);

            if (!$stock || $stock->statut !== 'disponible') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce stock n\'est plus disponible.',
                    'errors'  => [],
                ], 409);
            }

            if ($stock->eleveur_id === $acheteur->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas commander votre propre stock.',
                    'errors'  => [],
                ], 403);
            }

            if ($request->quantite > $stock->quantite_disponible) {
                return response()->json([
                    'success' => false,
                    'message' => "Quantité insuffisante. Disponible : {$stock->quantite_disponible}.",
                    'errors'  => ['quantite' => ["Maximum disponible : {$stock->quantite_disponible}."]],
                ], 422);
            }

            $poids_total     = round($request->quantite * $stock->poids_moyen_kg, 2);
            $montant_total   = intval(round($request->quantite * $stock->prix_par_kg * $stock->poids_moyen_kg));
            $commission      = intval(round($montant_total * 0.07));
            $montant_eleveur = $montant_total - $commission;

            $commande = Commande::create([
                'acheteur_id'              => $acheteur->id,
                'eleveur_id'               => $stock->eleveur_id,
                'stock_id'                 => $stock->id,
                'quantite'                 => $request->quantite,
                'poids_total'              => $poids_total,
                'montant_total'            => $montant_total,
                'commission_plateforme'    => $commission,
                'montant_eleveur'          => $montant_eleveur,
                'mode_paiement'            => $request->mode_paiement,
                'statut_paiement'          => 'en_attente',
                'statut_commande'          => 'confirmee',
                'adresse_livraison'        => $request->adresse_livraison,
                'date_livraison_souhaitee' => $request->date_livraison_souhaitee,
                'note_livraison'           => $request->note_livraison,
            ]);

            $nouvelleQuantite = $stock->quantite_disponible - $request->quantite;
            $stock->update([
                'quantite_disponible' => max(0, $nouvelleQuantite),
                'statut'              => $nouvelleQuantite <= 0 ? 'epuise' : 'reserve',
            ]);

            $commande->load(['stock', 'eleveur', 'acheteur']);

            return response()->json([
                'success' => true,
                'message' => 'Commande passée avec succès.',
                'data'    => new CommandeResource($commande),
            ], 201);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-04 — Annuler une commande
    // DELETE /api/acheteur/commandes/{id}
    // ══════════════════════════════════════════════════════════════

    public function destroy(Request $request, int $id): JsonResponse
    {
        $acheteur = $request->user();

        return DB::transaction(function () use ($acheteur, $id) {

            $commande = Commande::with('stock')->lockForUpdate()->find($id);

            if (!$commande) {
                return response()->json(['success' => false, 'message' => 'Commande introuvable.', 'errors' => []], 404);
            }

            if ($commande->acheteur_id !== $acheteur->id) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.', 'errors' => []], 403);
            }

            if ($commande->statut_commande !== 'confirmee') {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible d'annuler une commande « {$commande->statut_commande} ».",
                    'errors'  => [],
                ], 422);
            }

            $commande->update([
                'statut_commande' => 'annulee',
                'statut_paiement' => 'rembourse',
            ]);

            if ($commande->stock) {
                $commande->stock->update([
                    'quantite_disponible' => $commande->stock->quantite_disponible + $commande->quantite,
                    'statut'              => 'disponible',
                ]);
            }

            $commande->load(['stock', 'eleveur', 'acheteur']);

            return response()->json([
                'success' => true,
                'message' => 'Commande annulée avec succès. Le remboursement sera traité prochainement.',
                'data'    => new CommandeResource($commande),
            ], 200);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-06 — Confirmer réception livraison
    // POST /api/acheteur/commandes/{id}/confirmer-livraison
    // ══════════════════════════════════════════════════════════════

    /**
     * L'acheteur confirme avoir reçu sa commande.
     * Déclenche la libération immédiate de l'escrow vers l'éleveur.
     *
     * Règles métier :
     *   - La commande doit être au statut 'livree'
     *   - Le statut_paiement doit être 'paye' (pas encore libéré)
     *   - Appel à EscrowService::liberer() → statut_paiement = 'libere'
     *   - escrow_libere_at est horodaté
     *   - Le job LibererEscrowJob ne retraitera plus cette commande
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse  200 | 403 | 404 | 422
     */
    public function confirmerLivraison(Request $request, int $id): JsonResponse
    {
        $acheteur = $request->user();
        $commande = Commande::with(['stock', 'eleveur', 'acheteur'])->find($id);

        // ── 1. Existence ─────────────────────────────────────────────
        if (!$commande) {
            return response()->json([
                'success' => false,
                'message' => 'Commande introuvable.',
                'errors'  => [],
            ], 404);
        }

        // ── 2. Propriété ─────────────────────────────────────────────
        if ($commande->acheteur_id !== $acheteur->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à confirmer cette livraison.',
                'errors'  => [],
            ], 403);
        }

        // ── 3. Statut commande doit être 'livree' ─────────────────────
        if ($commande->statut_commande !== 'livree') {
            return response()->json([
                'success' => false,
                'message' => "La commande est actuellement « {$commande->statut_commande} ». La confirmation est uniquement possible après livraison.",
                'errors'  => [],
            ], 422);
        }

        // ── 4. Escrow pas déjà libéré ─────────────────────────────────
        if ($commande->statut_paiement === 'libere') {
            return response()->json([
                'success' => false,
                'message' => 'Les fonds ont déjà été libérés pour cette commande.',
                'errors'  => [],
            ], 422);
        }

        // ── 5. Vérifier statut_paiement = 'paye' ─────────────────────
        if ($commande->statut_paiement !== 'paye') {
            return response()->json([
                'success' => false,
                'message' => "Impossible de libérer les fonds : statut paiement = « {$commande->statut_paiement} ».",
                'errors'  => [],
            ], 422);
        }

        // ── 6. Libérer l'escrow ───────────────────────────────────────
        $this->escrowService->liberer($commande);

        $commande->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Réception confirmée. Les fonds ont été libérés à l\'éleveur.',
            'data'    => new CommandeResource($commande),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // CMD-11 — Détail d'une commande
    // GET /api/acheteur/commandes/{id}
    // ══════════════════════════════════════════════════════════════

    public function show(Request $request, int $id): JsonResponse
    {
        $commande = Commande::with(['stock', 'eleveur', 'acheteur'])->find($id);

        if (!$commande) {
            return response()->json(['success' => false, 'message' => 'Commande introuvable.', 'errors' => []], 404);
        }

        if ($commande->acheteur_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.', 'errors' => []], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détail de la commande récupéré avec succès.',
            'data'    => new CommandeResource($commande),
        ], 200);
    }
}