<?php

namespace App\Http\Controllers\Acheteur;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** STUB — sera remplacé au sprint Acheteur */
class FavoriController extends Controller
{
    public function __call(string $name, array $args): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Non implémenté', 'data' => null], 501);
    }
}