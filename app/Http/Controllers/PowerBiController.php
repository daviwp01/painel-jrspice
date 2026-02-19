<?php

namespace App\Http\Controllers;

use App\Services\PowerBiService;
use Inertia\Inertia;

class PowerBiController extends Controller
{
    public function index(PowerBiService $powerBiService)
    {
        // For now, we use the env variables. In a real app, this would come from the authenticated user.
        // Example: $user = auth()->user();
        // $config = $powerBiService->getEmbedConfig($user->email, ['User']);

        $config = $powerBiService->getEmbedConfig();

        return Inertia::render('PowerBi/Index', [
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
