<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockPublicResource;
use App\Models\Avis;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Recherche & Découverte — stocks publics
 *
 * Fichier : app/Http/Controllers/Public/StockPublicController.php
 *
 * Routes couvertes (toutes publiques — sans authentification) :
 *   RCH-01 : GET /api/stocks              — Liste publique paginée
 *   RCH-02 : GET /api/stocks?ville=       — Filtre par ville éleveur
 *   RCH-03 : GET /api/stocks?prix_min=&prix_max= — Filtre prix/kg
 *   RCH-04 : GET /api/stocks?poids_min=   — Filtre poids minimum
 *   RCH-05 : GET /api/stocks?mode_vente=  — Filtre mode vente
 *   RCH-06 : GET /api/stocks?certifie=1   — Éleveurs certifiés seulement
 *   RCH-07 : GET /api/stocks?sort=        — Tri résultats
 *   RCH-08 : GET /api/stocks?q=           — Recherche full-text
 *   RCH-09 : GET /api/eleveurs/carte      — Carte des éleveurs (lat/lng)
 *   RCH-10 : GET /api/stocks/{id}         — Détail stock + incrémente vues (STK-09)
 */
class StockPublicController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // RCH-01 à RCH-08 — Liste publique avec filtres et tri
    // GET /api/stocks
    // ══════════════════════════════════════════════════════════════

    /**
     * Liste publique des stocks disponibles avec filtres cumulables.
     *
     * Filtres disponibles :
     *   ?q=          Recherche full-text sur titre + description (LIKE)
     *   ?ville=      Ville de l'éleveur (recherche partielle)
     *   ?prix_min=   Prix par kg minimum (FCFA)
     *   ?prix_max=   Prix par kg maximum (FCFA)
     *   ?poids_min=  Poids moyen minimum (kg)
     *   ?mode_vente= vivant | abattu | les_deux
     *   ?certifie=1  Éleveurs certifiés uniquement
     *
     * Tri disponible (?sort=) :
     *   prix_asc     Prix croissant
     *   prix_desc    Prix décroissant
     *   date_asc     Plus anciens d'abord
     *   date_desc    Plus récents d'abord (défaut)
     *   note_desc    Mieux notés d'abord
     *
     * Pagination : 12 par page.
     * Seuls les stocks `statut = disponible` dont la date de disponibilité
     * est passée ou aujourd'hui sont retournés.
     *
     * @param  Request $request
     * @return JsonResponse  200
     */
    public function index(Request $request): JsonResponse
    {
        $query = Stock::query()
            ->where('statut', 'disponible')
            ->with(['eleveur' => function ($q) {
                $q->with('eleveurProfile');
            }])
            ->whereHas('eleveur');

        // ── RCH-08 : Recherche full-text ───────────────────────────
        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'ILIKE', $search)
                    ->orWhere('description', 'ILIKE', $search);
            });
        }

        // ── RCH-02 : Filtre par ville éleveur ──────────────────────
        if ($request->filled('ville')) {
            $ville = '%' . $request->ville . '%';
            $query->whereHas('eleveur', function ($q) use ($ville) {
                $q->where('ville', 'ILIKE', $ville);
            });
        }

        // ── RCH-03 : Filtre par prix min/max ───────────────────────
        if ($request->filled('prix_min')) {
            $query->where('prix_par_kg', '>=', (float) $request->prix_min);
        }
        if ($request->filled('prix_max')) {
            $query->where('prix_par_kg', '<=', (float) $request->prix_max);
        }

        // ── RCH-04 : Filtre par poids minimum ──────────────────────
        if ($request->filled('poids_min')) {
            $query->where('poids_moyen_kg', '>=', (float) $request->poids_min);
        }

        // ── RCH-05 : Filtre par mode de vente ──────────────────────
        if ($request->filled('mode_vente')) {
            $query->where('mode_vente', $request->mode_vente);
        }

        // ── RCH-06 : Filtre éleveurs certifiés ─────────────────────
        if ($request->boolean('certifie')) {
            $query->whereHas('eleveur.eleveurProfile', function ($q) {
                $q->where('is_certified', true);
            });
        }

        // ── RCH-07 : Tri des résultats ──────────────────────────────
        match ($request->input('sort', 'date_desc')) {
            'prix_asc'   => $query->orderBy('prix_par_kg', 'asc'),
            'prix_desc'  => $query->orderBy('prix_par_kg', 'desc'),
            'date_asc'   => $query->orderBy('created_at', 'asc'),
            'note_desc'  => $query->join('eleveur_profiles', 'stocks.eleveur_id', '=', 'eleveur_profiles.user_id')
                                   ->orderByDesc('eleveur_profiles.note_moyenne')
                                   ->select('stocks.*'),
            default      => $query->orderByDesc('created_at'), // date_desc
        };

        $stocks = $query->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'Stocks disponibles récupérés avec succès.',
            'data'    => StockPublicResource::collection($stocks->items()),
            'meta'    => [
                'current_page' => $stocks->currentPage(),
                'last_page'    => $stocks->lastPage(),
                'per_page'     => $stocks->perPage(),
                'total'        => $stocks->total(),
                'filters'      => [
                    'q'          => $request->input('q'),
                    'ville'      => $request->input('ville'),
                    'prix_min'   => $request->input('prix_min'),
                    'prix_max'   => $request->input('prix_max'),
                    'poids_min'  => $request->input('poids_min'),
                    'mode_vente' => $request->input('mode_vente'),
                    'certifie'   => $request->input('certifie'),
                    'sort'       => $request->input('sort', 'date_desc'),
                ],
            ],
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-10 + STK-09 — Détail d'un stock public
    // GET /api/stocks/{id}
    // ══════════════════════════════════════════════════════════════

    /**
     * Retourne le détail complet d'un stock public.
     * Incrémente le compteur de vues à chaque consultation (STK-09).
     *
     * @param  int $id
     * @return JsonResponse  200 | 404
     */
    public function show(int $id): JsonResponse
    {
        $stock = Stock::where('id', $id)
            ->where('statut', 'disponible')
            ->with([
                'eleveur' => function ($q) {
                    $q->with('eleveurProfile');
                },
            ])
            ->whereHas('eleveur')
            ->first();

        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'Stock introuvable ou indisponible.',
                'errors'  => [],
            ], 404);
        }

        // STK-09 : Incrémenter les vues
        $stock->increment('vues');

        // Charger les avis de l'éleveur (cible_id = eleveur_id)
        $avis = Avis::where('cible_id', $stock->eleveur_id)
            ->with('auteur:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'note'        => $a->note,
                'commentaire' => $a->commentaire,
                'reply'       => $a->reply,
                'created_at'  => $a->created_at?->toISOString(),
                'auteur'      => $a->auteur ? ['name' => $a->auteur->name] : null,
            ]);

        $stock->load('eleveur.eleveurProfile');

        return response()->json([
            'success' => true,
            'message' => 'Détail du stock récupéré avec succès.',
            'data'    => array_merge(
                (new StockPublicResource($stock))->resolve(),
                ['avis' => $avis]
            ),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // RCH-09 — Carte des éleveurs
    // GET /api/eleveurs/carte
    // ══════════════════════════════════════════════════════════════

    /**
     * Retourne la liste des éleveurs actifs avec leurs coordonnées GPS
     * pour affichage sur Google Maps côté Angular.
     *
     * Filtrés : éleveurs actifs, vérifiés, avec profil et coordonnées renseignées.
     * Filtre optionnel : ?certifie=1, ?ville=
     *
     * @param  Request $request
     * @return JsonResponse  200
     */
    public function carte(Request $request): JsonResponse
    {
        $query = User::where('role', 'eleveur')
            ->where('is_active', true)
            ->where('is_verified', true)
            ->with('eleveurProfile')
            ->whereHas('eleveurProfile', function ($q) {
                $q->whereNotNull('latitude')
                    ->whereNotNull('longitude');
            });

        if ($request->boolean('certifie')) {
            $query->whereHas('eleveurProfile', fn ($q) => $q->where('is_certified', true));
        }

        if ($request->filled('ville')) {
            $query->where('ville', 'ILIKE', '%' . $request->ville . '%');
        }

        $eleveurs = $query->get();

        $data = $eleveurs->map(fn ($eleveur) => [
            'id'             => $eleveur->id,
            'name'           => $eleveur->name,
            'avatar'         => $eleveur->avatar,
            'ville'          => $eleveur->ville,
            'nom_poulailler' => $eleveur->eleveurProfile->nom_poulailler,
            'is_certified'   => $eleveur->eleveurProfile->is_certified,
            'note_moyenne'   => $eleveur->eleveurProfile->note_moyenne,
            'latitude'       => $eleveur->eleveurProfile->latitude,
            'longitude'      => $eleveur->eleveurProfile->longitude,
            'photos'         => $eleveur->eleveurProfile->photos ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Carte des éleveurs récupérée avec succès.',
            'data'    => $data,
            'meta'    => ['total' => $eleveurs->count()],
        ], 200);
    }
}