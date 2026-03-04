<?php

// routes/api.php — MACIF CHICKEN
// Toutes les routes préfixées /api/ (via bootstrap/app.php ou RouteServiceProvider)

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Public\StockPublicController;
use App\Http\Controllers\Eleveur\TransactionController;
/*
|─────────────────────────────────────────────────────────────
| AUTH (publique)
|─────────────────────────────────────────────────────────────
*/
Route::prefix('auth')->group(function () {
    Route::post('/register',                    [AuthController::class, 'register']);
    Route::post('/login',                       [AuthController::class, 'login']);
    Route::post('/forgot-password',             [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password',              [AuthController::class, 'resetPassword']);
    Route::get('/verify-email/{token}',         [AuthController::class, 'verifyEmail']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout',                  [AuthController::class, 'logout']);
        Route::get('/me',                       [AuthController::class, 'me']);
        Route::put('/profile',                  [AuthController::class, 'updateProfile']);
    });
});

/*
|─────────────────────────────────────────────────────────────
| PUBLIC (sans auth)
|─────────────────────────────────────────────────────────────
*/
Route::get('/stocks',                           [\App\Http\Controllers\Public\StockPublicController::class, 'index']);
Route::get('/stocks/{id}',                      [\App\Http\Controllers\Public\StockPublicController::class, 'show']);
Route::get('/eleveurs/{id}/public',             [\App\Http\Controllers\Public\EleveurPublicController::class, 'show']);

// Public — Recherche & Découverte (Sprint 4)
Route::get('/stocks', [StockPublicController::class, 'index']);
Route::get('/stocks/{id}', [StockPublicController::class, 'show']);
Route::get('/eleveurs/carte', [StockPublicController::class, 'carte']);

/*
|─────────────────────────────────────────────────────────────
| ÉLEVEUR (auth + role:eleveur)
|─────────────────────────────────────────────────────────────
*/
Route::middleware(['auth:sanctum', 'role.eleveur'])->prefix('eleveur')->group(function () {
    Route::get('/profile',                      [\App\Http\Controllers\Eleveur\ProfileController::class, 'show']);
    Route::put('/profile',                      [\App\Http\Controllers\Eleveur\ProfileController::class, 'update']);
    Route::get('/stocks',                       [\App\Http\Controllers\Eleveur\StockController::class, 'index']);
    Route::post('/stocks',                      [\App\Http\Controllers\Eleveur\StockController::class, 'store']);
    Route::put('/stocks/{id}',                  [\App\Http\Controllers\Eleveur\StockController::class, 'update']);
    Route::delete('/stocks/{id}',               [\App\Http\Controllers\Eleveur\StockController::class, 'destroy']);
    Route::get('/commandes',                    [\App\Http\Controllers\Eleveur\CommandeController::class, 'index']);
    Route::put('/commandes/{id}',               [\App\Http\Controllers\Eleveur\CommandeController::class, 'update']);
    Route::get('/dashboard',                    [\App\Http\Controllers\Eleveur\DashboardController::class, 'index']);
    Route::get('/avis',                         [\App\Http\Controllers\Eleveur\AvisController::class, 'index']);
    Route::put('/avis/{id}/reply',              [\App\Http\Controllers\Eleveur\AvisController::class, 'reply']);
    Route::get('/abonnement',                   [\App\Http\Controllers\Eleveur\AbonnementController::class, 'show']);
    Route::get('/transactions',                 [\App\Http\Controllers\Eleveur\TransactionController::class, 'index']);
    // PAY-06 — Reçu PDF (dans le groupe eleveur)
Route::get('/transactions/{id}/recu', [TransactionController::class, 'recu']);
});

/*
|─────────────────────────────────────────────────────────────
| ACHETEUR (auth + role:acheteur)
|─────────────────────────────────────────────────────────────
*/
Route::middleware(['auth:sanctum', 'role.acheteur'])->prefix('acheteur')->group(function () {
    Route::get('/profile',                      [\App\Http\Controllers\Acheteur\ProfileController::class, 'show']);
    Route::put('/profile',                      [\App\Http\Controllers\Acheteur\ProfileController::class, 'update']);
    Route::post('/commandes',                   [\App\Http\Controllers\Acheteur\CommandeController::class, 'store']);
    Route::get('/commandes',                    [\App\Http\Controllers\Acheteur\CommandeController::class, 'index']);
    Route::get('/commandes/{id}',               [\App\Http\Controllers\Acheteur\CommandeController::class, 'show']);
    // CMD-04 — Annulation acheteur
    Route::delete('/commandes/{id}',            [\App\Http\Controllers\Acheteur\CommandeController::class, 'destroy']);
    Route::get('/dashboard',                    [\App\Http\Controllers\Acheteur\DashboardController::class, 'index']);
    Route::post('/favoris/{eleveur_id}',        [\App\Http\Controllers\Acheteur\FavoriController::class, 'store']);
    Route::delete('/favoris/{eleveur_id}',      [\App\Http\Controllers\Acheteur\FavoriController::class, 'destroy']);
    Route::get('/favoris',                      [\App\Http\Controllers\Acheteur\FavoriController::class, 'index']);
});

/*
|─────────────────────────────────────────────────────────────
| COMMANDES PARTAGÉES (auth — eleveur ou acheteur)
|─────────────────────────────────────────────────────────────
*/
Route::middleware('auth:sanctum')->prefix('commandes')->group(function () {
    Route::post('/{id}/confirmer-livraison',    [\App\Http\Controllers\Shared\CommandeSharedController::class, 'confirmerLivraison']);
    Route::post('/{id}/litige',                 [\App\Http\Controllers\Shared\CommandeSharedController::class, 'ouvrirLitige']);
});

/*
|─────────────────────────────────────────────────────────────
| PAIEMENTS
|─────────────────────────────────────────────────────────────
*/
Route::middleware('auth:sanctum')->post('/paiements/initier',  [\App\Http\Controllers\Shared\PaiementController::class, 'initier']);
Route::post('/paiements/webhook',                              [\App\Http\Controllers\Shared\PaiementController::class, 'webhook']); // public

/*
|─────────────────────────────────────────────────────────────
| AVIS
|─────────────────────────────────────────────────────────────
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/avis',                        [\App\Http\Controllers\Shared\AvisController::class, 'store']);
    Route::post('/avis/{id}/signaler',          [\App\Http\Controllers\Shared\AvisController::class, 'signaler']);
});

/*
|─────────────────────────────────────────────────────────────
| NOTIFICATIONS
|─────────────────────────────────────────────────────────────
*/
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/',                             [\App\Http\Controllers\Shared\NotificationController::class, 'index']);
    Route::put('/{id}/lu',                      [\App\Http\Controllers\Shared\NotificationController::class, 'marquerLu']);
    Route::put('/tout-lire',                    [\App\Http\Controllers\Shared\NotificationController::class, 'toutLire']);
});

/*
|─────────────────────────────────────────────────────────────
| ADMIN (auth + role:admin)
|─────────────────────────────────────────────────────────────
*/
Route::middleware(['auth:sanctum', 'role.admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard',                    [\App\Http\Controllers\Admin\DashboardController::class, 'index']);
    Route::get('/users',                        [\App\Http\Controllers\Admin\UserController::class, 'index']);
    Route::put('/users/{id}/toggle-status',     [\App\Http\Controllers\Admin\UserController::class, 'toggleStatus']);
    Route::put('/users/{id}/certifier',         [\App\Http\Controllers\Admin\UserController::class, 'certifier']);
    Route::get('/stocks',                       [\App\Http\Controllers\Admin\StockController::class, 'index']);
    Route::put('/stocks/{id}/moderer',          [\App\Http\Controllers\Admin\StockController::class, 'moderer']);
    Route::get('/commandes',                    [\App\Http\Controllers\Admin\CommandeController::class, 'index']);
    Route::get('/litiges',                      [\App\Http\Controllers\Admin\LitigeController::class, 'index']);
    Route::put('/litiges/{id}/resoudre',        [\App\Http\Controllers\Admin\LitigeController::class, 'resoudre']);
    Route::get('/finances',                     [\App\Http\Controllers\Admin\FinanceController::class, 'index']);
    Route::get('/finances/export',              [\App\Http\Controllers\Admin\FinanceController::class, 'export']);
});
