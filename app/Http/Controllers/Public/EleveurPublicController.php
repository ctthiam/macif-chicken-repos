<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** STUB — sera remplacé au sprint Public */
class EleveurPublicController extends Controller
{
    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Non implémenté', 'data' => null], 501);
    }
}