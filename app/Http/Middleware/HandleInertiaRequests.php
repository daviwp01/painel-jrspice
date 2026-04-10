<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'settings' => [
                'legal_privacy_policy' => \Illuminate\Support\Facades\Cache::remember('setting_privacy', 3600, fn() => \App\Models\Setting::get('legal_privacy_policy')),
                'legal_terms_of_use' => \Illuminate\Support\Facades\Cache::remember('setting_terms', 3600, fn() => \App\Models\Setting::get('legal_terms_of_use')),
            ],
            'dashboardPages' => function() use ($request) {
                $user = $request->user();
                if (!$user) return [];

                return \App\Models\DashboardPage::where('is_active', true)
                    ->orderBy('order')
                    ->get()
                    ->filter(fn($page) => $user->canAccessPage($page->slug))
                    ->map(fn($page) => [
                        'id' => $page->id,
                        'title' => $page->title,
                        'slug' => $page->slug,
                    ])
                    ->values();
            },

        ];
    }
}
