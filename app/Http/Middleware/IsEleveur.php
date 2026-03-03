<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsEleveur
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->role !== 'eleveur') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux éleveurs.',
                'errors'  => [],
            ], 403);
        }
        return $next($request);
    }
}