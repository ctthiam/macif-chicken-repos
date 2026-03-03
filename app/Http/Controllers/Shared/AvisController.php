<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** STUB — sera remplacé au sprint Shared */
class AvisController extends Controller
{
    public function __call(string $name, array $args): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Non implémenté', 'data' => null], 501);
    }
}