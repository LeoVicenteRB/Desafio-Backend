<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check endpoint.
     */
    public function index(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'ok';
        } catch (\Exception $e) {
            $dbStatus = 'error';
        }

        return response()->json([
            'status' => 'ok',
            'database' => $dbStatus,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

