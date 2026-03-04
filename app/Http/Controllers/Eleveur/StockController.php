<?php

namespace App\Http\Controllers\Eleveur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Eleveur\CreateStockRequest;
use App\Http\Resources\StockResource;
use App\Models\Abonnement;
use App\Models\Stock;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Gestion des stocks éleveur
 *
 * Fichier : app/Http/Controllers/Eleveur/StockController.php
 *
 * Routes couvertes :
 *   STK-01 : POST   /api/eleveur/stocks         — Créer annonce
 *   STK-02 : inclus dans STK-01                 — Upload photos
 *   STK-03 : PUT    /api/eleveur/stocks/{id}    — Modifier (à venir)
 *   STK-04 : DELETE /api/eleveur/stocks/{id}    — Supprimer (à venir)
 *   STK-06 : GET    /api/eleveur/stocks         — Liste paginée (à venir)
 *   STK-08 : vérification limite abonnement     — Inclus dans STK-01
 */
class StockController extends Controller
{
    public function __construct(
        private readonly StorageService $storageService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // STK-06 — Liste de mes stocks
    // GET /api/eleveur/stocks
    // ══════════════════════════════════════════════════════════════

    /**
     * Retourne la liste paginée des stocks de l'éleveur connecté.
     * Pagination : 12 par page.
     * Filtres optionnels : statut, mode_vente.
     *
     * @param  Request $request
     * @return JsonResponse  200
     */
    public function index(Request $request): JsonResponse
    {
        $query = Stock::where('eleveur_id', $request->user()->id)
            ->orderByDesc('created_at');

        // Filtre optionnel par statut
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        // Filtre optionnel par mode_vente
        if ($request->filled('mode_vente')) {
            $query->where('mode_vente', $request->mode_vente);
        }

        $stocks = $query->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'Liste des stocks récupérée avec succès.',
            'data'    => StockResource::collection($stocks->items()),
            'meta'    => [
                'current_page' => $stocks->currentPage(),
                'last_page'    => $stocks->lastPage(),
                'per_page'     => $stocks->perPage(),
                'total'        => $stocks->total(),
            ],
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // STK-01 + STK-02 — Créer une annonce de stock
    // POST /api/eleveur/stocks
    // ══════════════════════════════════════════════════════════════

    /**
     * Crée une nouvelle annonce de stock pour l'éleveur connecté.
     *
     * STK-08 inclus : Vérifie la limite de stocks selon l'abonnement
     *   - Sans abonnement actif   → max 0 stocks (doit s'abonner)
     *   - Plan starter            → max 3 stocks actifs (disponible|reserve)
     *   - Plan pro                → max 10 stocks actifs
     *   - Plan premium            → illimité
     *
     * STK-02 inclus : Si des photos sont envoyées, elles sont uploadées
     * sur R2 via StorageService et les URLs sont stockées en JSON.
     *
     * @param  CreateStockRequest $request
     * @return JsonResponse  201 | 403 | 422
     */
    public function store(CreateStockRequest $request): JsonResponse
    {
        $user = $request->user();

        // ── STK-08 : Vérifier la limite selon l'abonnement ──────────
        $limitCheck = $this->checkAbonnementLimit($user->id);
        if (!$limitCheck['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $limitCheck['message'],
                'errors'  => ['abonnement' => $limitCheck['message']],
            ], 403);
        }

        // ── STK-02 : Upload des photos ───────────────────────────────
        $photos = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $photos[] = $this->storageService->uploadStockPhoto($photo, $user->id);
            }
        }

        // ── STK-01 : Créer le stock ──────────────────────────────────
        $stock = Stock::create([
            'eleveur_id'            => $user->id,
            'titre'                 => $request->titre,
            'description'           => $request->description,
            'quantite_disponible'   => $request->quantite_disponible,
            'poids_moyen_kg'        => $request->poids_moyen_kg,
            'prix_par_kg'           => $request->prix_par_kg,
            'prix_par_unite'        => $request->prix_par_unite,
            'mode_vente'            => $request->mode_vente,
            'date_disponibilite'    => $request->date_disponibilite,
            'date_peremption_stock' => $request->date_peremption_stock,
            'photos'                => $photos,
            'statut'                => 'disponible',
            'vues'                  => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Annonce de stock créée avec succès.',
            'data'    => new StockResource($stock),
        ], 201);
    }

    // ══════════════════════════════════════════════════════════════
    // STK-03 — Modifier une annonce (à venir)
    // PUT /api/eleveur/stocks/{id}
    // ══════════════════════════════════════════════════════════════

    /**
     * @param  int $id
     * @return JsonResponse
     */
    public function update(int $id): JsonResponse
    {
        // TODO STK-03
        return response()->json(['success' => false, 'message' => 'Non implémenté — STK-03.', 'data' => null], 501);
    }

    // ══════════════════════════════════════════════════════════════
    // STK-04 — Supprimer une annonce (à venir)
    // DELETE /api/eleveur/stocks/{id}
    // ══════════════════════════════════════════════════════════════

    /**
     * @param  int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // TODO STK-04
        return response()->json(['success' => false, 'message' => 'Non implémenté — STK-04.', 'data' => null], 501);
    }

    // ══════════════════════════════════════════════════════════════
    // Helper : Vérification limite abonnement (STK-08)
    // ══════════════════════════════════════════════════════════════

    /**
     * Vérifie si l'éleveur peut créer un nouveau stock selon son plan.
     *
     * Compte les stocks actifs (disponible + reserve) — pas les épuisés/expirés.
     * Un éleveur sans abonnement actif ne peut pas publier.
     *
     * @param  int $eleveurId
     * @return array{allowed: bool, message: string, current: int, limit: int|null}
     */
    private function checkAbonnementLimit(int $eleveurId): array
    {
        // Récupérer l'abonnement actif
        $abonnement = Abonnement::where('eleveur_id', $eleveurId)
            ->where('statut', 'actif')
            ->where('date_fin', '>=', now()->toDateString())
            ->latest()
            ->first();

        if (!$abonnement) {
            return [
                'allowed' => false,
                'message' => 'Vous devez avoir un abonnement actif pour publier des annonces.',
                'current' => 0,
                'limit'   => 0,
            ];
        }

        $limit = $abonnement->getStockLimit();

        // Premium = illimité
        if ($limit === null) {
            return ['allowed' => true, 'message' => '', 'current' => 0, 'limit' => null];
        }

        // Compter les stocks actifs (disponible + reserve)
        $currentCount = Stock::where('eleveur_id', $eleveurId)
            ->whereIn('statut', ['disponible', 'reserve'])
            ->count();

        if ($currentCount >= $limit) {
            $planLabel = ucfirst($abonnement->plan);
            return [
                'allowed' => false,
                'message' => "Votre plan {$planLabel} est limité à {$limit} annonces actives. Vous en avez actuellement {$currentCount}. Passez à un plan supérieur pour publier plus.",
                'current' => $currentCount,
                'limit'   => $limit,
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'current' => $currentCount,
            'limit'   => $limit,
        ];
    }
}