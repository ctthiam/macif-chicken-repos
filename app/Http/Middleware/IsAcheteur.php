<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAcheteur
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->role !== 'acheteur') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux acheteurs.',
                'errors'  => [],
            ], 403);
        }
        return $next($request);
    }
}