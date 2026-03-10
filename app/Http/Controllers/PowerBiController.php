<?php

namespace App\Http\Controllers;

use App\Services\PowerBiService;
use Inertia\Inertia;

class PowerBiController extends Controller
{
    public function index(PowerBiService $powerBiService)
    {
        $refresh = request()->has('refresh');

        // Pass user context if needed for RLS
        $config = $powerBiService->getEmbedConfig(null, null, $refresh);

        return Inertia::render('Dashboard', [
            'embedConfig' => $config,
        ]);
    }
    public function getConfig(PowerBiService $powerBiService)
    {
        try {
            return response()->json($powerBiService->getEmbedConfig());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
