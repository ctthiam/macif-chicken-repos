<?php

namespace App\Http\Controllers\Eleveur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Eleveur\CreateStockRequest;
use App\Http\Requests\Eleveur\UpdateStockRequest;
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
 *   STK-03 : PUT    /api/eleveur/stocks/{id}    — Modifier ✓
 *   STK-04 : DELETE /api/eleveur/stocks/{id}    — Supprimer ✓
 *   STK-06 : GET    /api/eleveur/stocks         — Liste paginée
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

    public function index(Request $request): JsonResponse
    {
        $query = Stock::where('eleveur_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->filled('statut'))     $query->where('statut', $request->statut);
        if ($request->filled('mode_vente')) $query->where('mode_vente', $request->mode_vente);

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

    public function store(CreateStockRequest $request): JsonResponse
    {
        $user = $request->user();

        $limitCheck = $this->checkAbonnementLimit($user->id);
        if (!$limitCheck['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $limitCheck['message'],
                'errors'  => ['abonnement' => $limitCheck['message']],
            ], 403);
        }

        $photos = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $photos[] = $this->storageService->uploadStockPhoto($photo, $user->id);
            }
        }

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
    // STK-03 — Modifier une annonce
    // PUT /api/eleveur/stocks/{id}
    // ══════════════════════════════════════════════════════════════

    /**
     * Modifie une annonce de stock appartenant à l'éleveur connecté.
     *
     * Règles métier :
     *  - Le stock doit appartenir à l'éleveur (403 sinon)
     *  - Un stock épuisé ou expiré ne peut pas être modifié (422)
     *  - Mise à jour partielle : seuls les champs présents sont modifiés
     *  - Photos : ajout de nouvelles + suppression ciblée via photos_a_supprimer[]
     *  - Le total de photos ne peut pas dépasser 5
     *
     * @param  UpdateStockRequest $request
     * @param  int                $id
     * @return JsonResponse  200 | 403 | 404 | 422
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $stock = Stock::where('id', $id)
            ->where('eleveur_id', $request->user()->id)
            ->first();

        if (!$stock) {
            return response()->json(['success' => false, 'message' => 'Stock introuvable.'], 404);
        }

        return response()->json(['success' => true, 'data' => $stock]);
    }

    public function update(UpdateStockRequest $request, int $id): JsonResponse
    {
        $user  = $request->user();
        $stock = Stock::find($id);

        // ── 1. Vérifier existence ────────────────────────────────────
        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'Annonce introuvable.',
                'errors'  => [],
            ], 404);
        }

        // ── 2. Vérifier propriété ────────────────────────────────────
        if ($stock->eleveur_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette annonce.',
                'errors'  => [],
            ], 403);
        }

        // ── 3. Vérifier statut modifiable ────────────────────────────
        // Si la requête ne touche qu'au statut (masquer/activer), on laisse passer.
        $onlyStatutChange = $request->has('statut') && count($request->except(['statut', '_method'])) === 0;
        if (!$onlyStatutChange && in_array($stock->statut, ['epuise', 'expire'])) {
            return response()->json([
                'success' => false,
                'message' => "Impossible de modifier une annonce avec le statut « {$stock->statut} ».",
                'errors'  => [],
            ], 422);
        }

        // ── 4. Mise à jour des champs texte ──────────────────────────
        $fields = [];
        if ($request->filled('titre'))               $fields['titre']               = $request->titre;
        if ($request->filled('description'))         $fields['description']         = $request->description;
        if ($request->has('quantite_disponible'))    $fields['quantite_disponible'] = $request->quantite_disponible;
        if ($request->has('poids_moyen_kg'))         $fields['poids_moyen_kg']      = $request->poids_moyen_kg;
        if ($request->has('prix_par_kg'))            $fields['prix_par_kg']         = $request->prix_par_kg;
        if ($request->has('prix_par_unite'))         $fields['prix_par_unite']      = $request->prix_par_unite;
        if ($request->has('mode_vente'))             $fields['mode_vente']          = $request->mode_vente;
        if ($request->filled('statut'))              $fields['statut']              = $request->statut;
        if ($request->has('date_disponibilite'))     $fields['date_disponibilite']  = $request->date_disponibilite;
        if ($request->has('date_peremption_stock'))  $fields['date_peremption_stock'] = $request->date_peremption_stock;

        // ── 5. Gestion des photos ─────────────────────────────────────
        $currentPhotos = $stock->photos ?? [];

        // Supprimer les photos demandées
        if ($request->has('photos_a_supprimer') && is_array($request->photos_a_supprimer)) {
            foreach ($request->photos_a_supprimer as $url) {
                $this->storageService->deleteByUrl($url);
            }
            $currentPhotos = array_values(
                array_filter($currentPhotos, fn ($url) => !in_array($url, $request->photos_a_supprimer))
            );
        }

        // Ajouter les nouvelles photos
        if ($request->hasFile('photos')) {
            $newPhotos = [];
            foreach ($request->file('photos') as $photo) {
                $newPhotos[] = $this->storageService->uploadStockPhoto($photo, $user->id);
            }
            $currentPhotos = array_slice(array_merge($currentPhotos, $newPhotos), 0, 5);
        }

        $fields['photos'] = $currentPhotos;

        // ── 6. Sauvegarder ───────────────────────────────────────────
        $stock->update($fields);

        return response()->json([
            'success' => true,
            'message' => 'Annonce de stock mise à jour avec succès.',
            'data'    => new StockResource($stock->fresh()),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // STK-04 — Supprimer une annonce
    // DELETE /api/eleveur/stocks/{id}
    // ══════════════════════════════════════════════════════════════

    /**
     * Supprime une annonce de stock appartenant à l'éleveur connecté.
     *
     * Règles métier :
     *  - Le stock doit appartenir à l'éleveur (403 sinon)
     *  - Un stock avec des commandes actives (confirmee, en_preparation,
     *    en_livraison) ne peut PAS être supprimé (422)
     *  - Les photos R2 sont supprimées du storage avant la suppression DB
     *
     * @param  Request $request
     * @param  int     $id
     * @return JsonResponse  200 | 403 | 404 | 422
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user  = $request->user();
        $stock = Stock::find($id);

        // ── 1. Vérifier existence ────────────────────────────────────
        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'Annonce introuvable.',
                'errors'  => [],
            ], 404);
        }

        // ── 2. Vérifier propriété ────────────────────────────────────
        if ($stock->eleveur_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette annonce.',
                'errors'  => [],
            ], 403);
        }

        // ── 3. Bloquer si commandes actives ──────────────────────────
        $commandesActives = $stock->commandes()
            ->whereIn('statut_commande', ['confirmee', 'en_preparation', 'en_livraison'])
            ->count();

        if ($commandesActives > 0) {
            return response()->json([
                'success' => false,
                'message' => "Impossible de supprimer cette annonce : {$commandesActives} commande(s) en cours lui sont associées.",
                'errors'  => [],
            ], 422);
        }

        // ── 4. Supprimer les photos du storage ───────────────────────
        foreach ($stock->photos ?? [] as $photoUrl) {
            $this->storageService->deleteByUrl($photoUrl);
        }

        // ── 5. Supprimer le stock ─────────────────────────────────────
        $stock->delete();

        return response()->json([
            'success' => true,
            'message' => 'Annonce de stock supprimée avec succès.',
            'data'    => null,
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // Helper : Vérification limite abonnement (STK-08)
    // ══════════════════════════════════════════════════════════════

    private function checkAbonnementLimit(int $eleveurId): array
    {
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

        if ($limit === null) {
            return ['allowed' => true, 'message' => '', 'current' => 0, 'limit' => null];
        }

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

        return ['allowed' => true, 'message' => '', 'current' => $currentCount, 'limit' => $limit];
    }
}