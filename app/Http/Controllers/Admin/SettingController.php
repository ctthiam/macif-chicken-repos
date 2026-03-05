<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur : Configuration plateforme (admin)
 *
 * Fichier : app/Http/Controllers/Admin/SettingController.php
 *
 * Routes (à ajouter dans api.php) :
 *   GET /api/admin/settings         — Lire tous les paramètres
 *   PUT /api/admin/settings         — Mettre à jour plusieurs paramètres
 */
class SettingController extends Controller
{
    // Clés autorisées (whitelist)
    private const CLES_AUTORISEES = [
        'taux_commission',
        'starter_prix',
        'pro_prix',
        'premium_prix',
    ];

    // ══════════════════════════════════════════════════════════════
    // GET /api/admin/settings
    // ══════════════════════════════════════════════════════════════

    public function index(): JsonResponse
    {
        $settings = Setting::whereIn('cle', self::CLES_AUTORISEES)
            ->get()
            ->mapWithKeys(fn ($s) => [$s->cle => $s->valeur]);

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ], 200);
    }

    // ══════════════════════════════════════════════════════════════
    // PUT /api/admin/settings
    // ══════════════════════════════════════════════════════════════

    /**
     * Met à jour un ou plusieurs paramètres.
     * Body JSON : { "taux_commission": "0.08", "starter_prix": "6000" }
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'taux_commission' => ['sometimes', 'numeric', 'between:0,1'],
            'starter_prix'    => ['sometimes', 'integer', 'min:0'],
            'pro_prix'        => ['sometimes', 'integer', 'min:0'],
            'premium_prix'    => ['sometimes', 'integer', 'min:0'],
        ]);

        $updated = [];

        foreach (self::CLES_AUTORISEES as $cle) {
            if ($request->has($cle)) {
                Setting::set($cle, $request->input($cle));
                $updated[$cle] = (string) $request->input($cle);
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($updated) . ' paramètre(s) mis à jour.',
            'data'    => $updated,
        ], 200);
    }
}