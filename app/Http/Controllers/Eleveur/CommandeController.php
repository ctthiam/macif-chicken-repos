<?php

namespace App\Http\Controllers\Eleveur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Eleveur\UpdateCommandeRequest;
use App\Http\Resources\CommandeResource;
use App\Models\Commande;
use App\Models\Stock;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Commandes côté éleveur
 *
 * Fichier : app/Http/Controllers/Eleveur/CommandeController.php
 *
 * Routes couvertes :
 *   CMD-10 : GET    /api/eleveur/commandes         — Historique éleveur
 *   CMD-03 : PUT    /api/eleveur/commandes/{id}    — action: confirmer
 *   CMD-04 : DELETE /api/acheteur/commandes/{id}   — Annulation acheteur (avant confirmation)
 *   CMD-05 : PUT    /api/eleveur/commandes/{id}    — action: en_livraison | livree
 */
class CommandeController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    // ══════════════════════════════════════════════════════════════
    // CMD-10 — Historique commandes éleveur
    // GET /api/eleveur/commandes
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $query = Commande::where('eleveur_id', $request->user()->id)
            ->with(['stock', 'acheteur'])
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
    // CMD-03 + CMD-05 — Workflow livraison éleveur
    // PUT /api/eleveur/commandes/{id}
    // ══════════════════════════════════════════════════════════════

    /**
     * Fait avancer le statut d'une commande selon l'action envoyée.
     *
     * Transitions autorisées :
     *   confirmer    : confirmee      → en_preparation  (CMD-03)
     *   en_livraison : en_preparation → en_livraison    (CMD-05)
     *   livree       : en_livraison   → livree           (CMD-05)
     *
     * Règles métier :
     *   - La commande doit appartenir à l'éleveur connecté
     *   - La transition doit être valide (pas de saut d'étape)
     *   - Quand 'livree' : le stock reste 'reserve' ou 'epuise' (pas de retour)
     *
     * @param  UpdateCommandeRequest $request
     * @param  int                   $id
     * @return JsonResponse  200 | 403 | 404 | 422
     */
    public function update(UpdateCommandeRequest $request, int $id): JsonResponse
    {
        $eleveur  = $request->user();
        $commande = Commande::with(['stock', 'acheteur'])->find($id);

        // ── 1. Vérifier existence ────────────────────────────────────
        if (!$commande) {
            return response()->json([
                'success' => false,
                'message' => 'Commande introuvable.',
                'errors'  => [],
            ], 404);
        }

        // ── 2. Vérifier propriété ────────────────────────────────────
        if ($commande->eleveur_id !== $eleveur->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette commande.',
                'errors'  => [],
            ], 403);
        }

        $action         = $request->action;
        $statutActuel   = $commande->statut_commande;

        // ── 3. Valider la transition ─────────────────────────────────
        $transitions = [
            'confirmer'    => ['from' => 'confirmee',      'to' => 'en_preparation'],
            'en_livraison' => ['from' => 'en_preparation', 'to' => 'en_livraison'],
            'livree'       => ['from' => 'en_livraison',   'to' => 'livree'],
        ];

        $transition = $transitions[$action];

        if ($statutActuel !== $transition['from']) {
            return response()->json([
                'success' => false,
                'message' => "Transition impossible. La commande est actuellement « {$statutActuel} » et ne peut pas passer à « {$transition['to']} » avec l'action « {$action} ».",
                'errors'  => [],
            ], 422);
        }

        // ── 4. Appliquer la transition ───────────────────────────────
        $commande->update(['statut_commande' => $transition['to']]);

        $messages = [
            'confirmer'    => 'Commande confirmée. Préparez la livraison.',
            'en_livraison' => 'Commande en cours de livraison.',
            'livree'       => 'Livraison marquée comme effectuée. En attente de confirmation de l\'acheteur.',
        ];

        // ── 5. Notifier l'acheteur du changement de statut ──────────
        $notifMessages = [
            'confirmer'    => ['titre' => '✅ Commande confirmée',       'msg' => 'Votre commande #' . $id . ' a été confirmée par ' . $eleveur->name . '. Préparation en cours.'],
            'en_livraison' => ['titre' => '🚚 Commande en livraison',    'msg' => 'Votre commande #' . $id . ' est en cours de livraison par ' . $eleveur->name . '.'],
            'livree'       => ['titre' => '📦 Commande livrée',          'msg' => 'Votre commande #' . $id . ' a été marquée comme livrée. Confirmez la réception pour libérer le paiement.'],
        ];
        $this->notificationService->notifier(
            userId:  $commande->acheteur_id,
            titre:   $notifMessages[$action]['titre'],
            message: $notifMessages[$action]['msg'],
            type:    'new_order',
            data:    ['commande_id' => $id, 'statut' => $transition['to']]
        );

        $commande->load(['stock', 'acheteur', 'eleveur']);

        return response()->json([
            'success' => true,
            'message' => $messages[$action],
            'data'    => new CommandeResource($commande),
        ], 200);
    }
}