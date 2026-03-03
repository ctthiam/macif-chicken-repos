<?php

namespace App\Http\Controllers\Eleveur;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** STUB — sera remplacé au sprint Eleveur */
class DashboardController extends Controller
{
    public function __call(string $name, array $args): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Non implémenté', 'data' => null], 501);
    }
}