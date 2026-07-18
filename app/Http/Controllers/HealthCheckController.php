<?php

namespace App\Http\Controllers;

use App\Services\DeploymentReadinessService;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function __invoke(DeploymentReadinessService $readiness): JsonResponse
    {
        $checks = $readiness->health();
        $healthy = ! in_array(false, $checks, true);

        return response()->json(['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks, 'timestamp' => now()->toIso8601String()], $healthy ? 200 : 503)
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
