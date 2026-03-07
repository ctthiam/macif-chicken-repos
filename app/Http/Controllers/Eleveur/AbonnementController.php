<?php

namespace App\Http\Controllers\Eleveur;

use App\Http\Controllers\Controller;
use App\Http\Resources\AbonnementResource;
use App\Models\Abonnement;
use App\Services\PaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Contrôleur : Abonnements éleveur
 *
 * Fichier : app/Http/Controllers/Eleveur/AbonnementController.php
 *
 * Routes :
 *   ABO-06 : GET  /api/eleveur/abonnement              — Plan actuel + infos
 *   ABO-01 : GET  /api/abonnements/plans               — Liste des plans (public)
 *   ABO-02 : POST /api/eleveur/abonnement/souscrire    — Souscrire via PayTech
 */
class AbonnementController extends Controller
{
    public function __construct(
        private readonly PaiementService $paiementService
    ) {}

    // ══════════════════════════════════════════════════════════════
    // ABO-06 — Page gestion abonnement éleveur
    // GET /api/eleveur/abonnement
    // ══════════════════════════════════════════════════════════════

    /**
     * Retourne l'abonnement actif de l'éleveur connecté.
     * Si aucun abonnement actif, retourne null avec les plans disponibles.
     *
     * @param  Request $request
     * @return JsonResponse  200
     */
    public function show(Request $request): JsonResponse
    {
        $eleveur = $request->user();

        $abonnement = Abonnement::where('eleveur_id', $eleveur->id)
            ->where('statut', 'actif')
            ->where('date_fin', '>=', now())
            ->latest()
            ->first();

        // Nombre de stocks actifs (disponible + reserve)
        $stocksActifs = $eleveur->stocks()
            ->whereIn('statut', ['disponible', 'reserve'])
            ->count();

        return response()->json([
            'success'      => true,
            'message'      => 'Abonnement récupéré avec succès.',
            'data'         => $abonnement ? new AbonnementResource($abonnement) : null,
            'stocks_actifs'=> $stocksActifs,
            'plans'        => $this->getPlansData(),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-01 — Liste des plans (route publique)
    // GET /api/abonnements/plans
    // ══════════════════════════════════════════════════════════════

    public function plans(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Plans disponibles.',
            'data'    => $this->getPlansData(),
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // ABO-02 — Souscrire / renouveler un abonnement
    // POST /api/eleveur/abonnement/souscrire
    // ══════════════════════════════════════════════════════════════

    /**
     * Initie un paiement PayTech pour un abonnement.
     *
     * Flow :
     *   1. Valide le plan choisi
     *   2. Crée un Abonnement en DB avec statut 'suspendu' (en attente paiement)
     *   3. Initie le paiement PayTech → retourne l'URL de paiement
     *   4. Le webhook /api/paiements/webhook-abonnement active l'abonnement
     *
     * Si l'éleveur a déjà un abonnement actif : création d'un renouvellement
     * qui débutera à la fin de l'abonnement actuel.
     *
     * @param  Request $request
     * @return JsonResponse  200 | 422 | 502
     */
    public function souscrire(Request $request): JsonResponse
    {
        $request->validate([
            'plan'             => ['required', 'string', 'in:starter,pro,premium'],
            'methode_paiement' => ['required', 'string', 'in:wave,orange_money,free_money'],
        ]);

        $eleveur = $request->user();
        $plan    = $request->plan;

        // Calcul des dates
        $abonnementActif = Abonnement::where('eleveur_id', $eleveur->id)
            ->where('statut', 'actif')
            ->where('date_fin', '>=', now())
            ->latest()
            ->first();

        // Si abonnement actif : renouvellement depuis la date de fin actuelle
        $dateDebut = $abonnementActif
            ? $abonnementActif->date_fin->addDay()
            : now()->startOfDay();

        $dateFin   = $dateDebut->copy()->addDays(Abonnement::DUREE_JOURS);
        $reference = 'ABO-' . strtoupper(Str::random(10));

        return DB::transaction(function () use ($eleveur, $plan, $dateDebut, $dateFin, $reference, $request) {

            // Créer l'abonnement en attente de paiement
            $abonnement = Abonnement::create([
                'eleveur_id'        => $eleveur->id,
                'plan'              => $plan,
                'prix_mensuel'      => Abonnement::PRIX[$plan],
                'date_debut'        => $dateDebut,
                'date_fin'          => $dateFin,
                'statut'            => 'suspendu', // activé après paiement webhook
                'methode_paiement'  => $request->methode_paiement,
                'reference_paiement'=> $reference,
            ]);

            // Initier le paiement PayTech
            // En mode développement (APP_ENV=local) sans clés PayTech configurées,
            // on active directement l'abonnement pour permettre les tests.
            $payTechKey = config('services.paytech.api_key');
            if (app()->environment('local') && empty($payTechKey)) {
                // Mode test : activer directement sans paiement réel
                $abonnement->update(['statut' => 'actif']);
                return response()->json([
                    'success'     => true,
                    'message'     => "Abonnement {$plan} activé (mode test — paiement simulé).",
                    'payment_url' => null,
                    'reference'   => $reference,
                    'abonnement'  => new AbonnementResource($abonnement),
                ], 200);
            }

            $result = $this->paiementService->initierPaiementAbonnement(
                eleveur:    $eleveur,
                abonnement: $abonnement,
            );

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            return response()->json([
                'success'     => true,
                'message'     => "Abonnement {$plan} initié. Procédez au paiement.",
                'payment_url' => $result['payment_url'],
                'reference'   => $reference,
                'abonnement'  => new AbonnementResource($abonnement),
            ], 200);
        });
    }

    // ══════════════════════════════════════════════════════════════
    // Helper — Données des plans
    // ══════════════════════════════════════════════════════════════

    private function getPlansData(): array
    {
        return [
            [
                'plan'        => 'starter',
                'prix'        => Abonnement::PRIX['starter'],
                'stocks_max'  => Abonnement::STOCK_LIMIT['starter'],
                'description' => 'Idéal pour démarrer — jusqu\'à 3 annonces simultanées.',
            ],
            [
                'plan'        => 'pro',
                'prix'        => Abonnement::PRIX['pro'],
                'stocks_max'  => Abonnement::STOCK_LIMIT['pro'],
                'description' => 'Pour les éleveurs actifs — jusqu\'à 10 annonces simultanées.',
            ],
            [
                'plan'        => 'premium',
                'prix'        => Abonnement::PRIX['premium'],
                'stocks_max'  => null,
                'description' => 'Annonces illimitées — accès prioritaire et badge certifié.',
            ],
        ];
    }
}