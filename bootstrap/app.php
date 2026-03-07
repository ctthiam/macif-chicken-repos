<?php

// bootstrap/app.php — Laravel 11 (pas de Kernel.php, tout ici)

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ─── Sanctum stateful middleware pour SPA ───────────
        $middleware->statefulApi();

         // Désactiver CSRF pour les routes API
    $middleware->validateCsrfTokens(except: [
        'api/*',
    ]);

        // ─── Aliases middleware personnalisés ────────────────
        $middleware->alias([
            'role.admin'    => \App\Http\Middleware\IsAdmin::class,
            'role.eleveur'  => \App\Http\Middleware\IsEleveur::class,
            'role.acheteur' => \App\Http\Middleware\IsAcheteur::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ─── Retourner les erreurs en JSON pour l'API ────────
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié. Veuillez vous connecter.',
                    'errors'  => [],
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ressource introuvable.',
                    'errors'  => [],
                ], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Action non autorisée.',
                    'errors'  => [],
                ], 403);
            }
        });
    })->create();
