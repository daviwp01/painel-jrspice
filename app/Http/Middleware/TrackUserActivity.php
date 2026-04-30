<?php

namespace App\Http\Middleware;

use App\Services\UserActivityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    /**
     * Throttle: only persist a heartbeat if at least this many
     * seconds have passed since the last write, to reduce DB load.
     */
    private const THROTTLE_SECONDS = 30;

    public function __construct(
        private readonly UserActivityService $activityService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            // Always update the lightweight last_activity_at column
            $user->update(['last_activity_at' => now()]);

            // Throttle the heavier session heartbeat
            $cacheKey = "user_heartbeat_throttle_{$user->id}";
            if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                $this->activityService->heartbeat($user, $request);
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, self::THROTTLE_SECONDS);
            }
        }

        return $next($request);
    }
}
