<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use App\Models\UserSearchLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserActivityService
{
    /**
     * Threshold in minutes – if the last heartbeat was longer ago than this,
     * the session is considered expired (e.g. user closed the tab).
     */
    private const SESSION_TIMEOUT_MINUTES = 10;

    /**
     * Threshold in minutes to consider a user "online".
     */
    private const ONLINE_THRESHOLD_MINUTES = 5;

    // ─── Session Lifecycle ──────────────────────────────────────

    /**
     * Start a new session for the user (called on Login event).
     */
    public function startSession(User $user, Request $request): UserSession
    {
        // Close any stale active sessions first
        $this->closeStaleSessionsForUser($user);

        return UserSession::create([
            'user_id'           => $user->id,
            'ip_address'        => $request->ip(),
            'user_agent'        => $request->userAgent(),
            'started_at'        => now(),
            'last_heartbeat_at' => now(),
            'is_active'         => true,
        ]);
    }

    /**
     * End the active session for the user (called on Logout event).
     */
    public function endSession(User $user): void
    {
        $session = UserSession::forUser($user->id)->active()->latest('started_at')->first();

        if ($session) {
            $this->closeSession($session);
        }
    }

    /**
     * Record a heartbeat – update the active session's last_heartbeat_at
     * and recalculate duration. Uses a throttled approach to avoid
     * hitting the DB on every single request.
     */
    public function heartbeat(User $user, Request $request): void
    {
        $session = UserSession::forUser($user->id)->active()->latest('started_at')->first();

        if (!$session) {
            // No active session found (e.g. server restarted, session was cleared).
            // Start a new one to prevent data loss.
            $this->startSession($user, $request);
            return;
        }

        // If the last heartbeat was too long ago, the session was probably abandoned.
        // Close it and start a new one.
        if ($session->last_heartbeat_at->diffInMinutes(now()) > self::SESSION_TIMEOUT_MINUTES) {
            $this->closeSession($session);
            $this->startSession($user, $request);
            return;
        }

        // Normal heartbeat – update duration
        $session->update([
            'last_heartbeat_at' => now(),
            'duration_seconds'  => $session->started_at->diffInSeconds(now()),
        ]);
    }

    // ─── Query / Analytics ──────────────────────────────────────

    /**
     * Get paginated user activity data for the admin Activity page.
     *
     * Performance: fetches session aggregates for the whole page in ONE
     * extra query using a subquery JOIN — no N+1.
     */
    public function getActivityOverview(int $perPage = 12)
    {
        // Single subquery: COUNT, SUM and AVG per user_id
        $sessionAggSub = UserSession::query()
            ->selectRaw('
                user_id,
                COUNT(*)                    AS total_sessions,
                COALESCE(SUM(duration_seconds), 0)  AS total_seconds,
                COALESCE(AVG(duration_seconds), 0)  AS avg_seconds
            ')
            ->groupBy('user_id');

        return User::query()
            ->leftJoinSub($sessionAggSub, 'sess', 'users.id', '=', 'sess.user_id')
            ->select(
                'users.id', 'users.name', 'users.email', 'users.is_master',
                'users.last_login_at', 'users.last_activity_at',
                'users.email_notified_at', 'users.email_clicked_at',
                DB::raw('COALESCE(sess.total_sessions, 0) AS total_sessions'),
                DB::raw('COALESCE(sess.total_seconds, 0)  AS total_seconds'),
                DB::raw('COALESCE(sess.avg_seconds, 0)    AS avg_seconds')
            )
            ->orderByRaw('users.last_activity_at IS NULL, users.last_activity_at DESC')
            ->paginate($perPage)
            ->through(function ($user) {
                $totalSec = (int) $user->total_seconds;
                $avgSec   = (int) $user->avg_seconds;

                return [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'is_master'      => (bool) $user->is_master,
                    'is_online'      => $user->last_activity_at &&
                                        \Carbon\Carbon::parse($user->last_activity_at)
                                            ->gt(now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES)),
                    'last_login'     => $user->last_login_at
                                        ? \Carbon\Carbon::parse($user->last_login_at)->diffForHumans()
                                        : __('Never'),
                    'last_activity'  => $user->last_activity_at
                                        ? \Carbon\Carbon::parse($user->last_activity_at)->diffForHumans()
                                        : __('None'),
                    'notified_at'    => $user->email_notified_at
                                        ? \Carbon\Carbon::parse($user->email_notified_at)->diffForHumans()
                                        : null,
                    'clicked_at'     => $user->email_clicked_at
                                        ? \Carbon\Carbon::parse($user->email_clicked_at)->diffForHumans()
                                        : null,
                    'total_sessions' => (int) $user->total_sessions,
                    'total_time'     => $this->formatDuration($totalSec),
                    'avg_time'       => $this->formatDuration($avgSec),
                ];
            });
    }

    /**
     * Get detailed session logs for a specific user (for the detail modal).
     */
    public function getUserSessionLogs(int $userId, int $perPage = 15)
    {
        return UserSession::forUser($userId)
            ->orderByDesc('started_at')
            ->paginate($perPage)
            ->through(function (UserSession $session) {
                return [
                    'id'             => $session->id,
                    'started_at'     => $session->started_at->format('d/m/Y H:i'),
                    'ended_at'       => $session->ended_at ? $session->ended_at->format('d/m/Y H:i') : __('Active'),
                    'duration'       => $session->formatted_duration,
                    'ip_address'     => $session->ip_address,
                    'browser'        => $session->browser,
                    'is_active'      => $session->is_active,
                ];
            });
    }

    /**
     * Clear all activity metadata + session history for every user.
     */
    public function clearAllActivity(): void
    {
        User::query()->update([
            'last_login_at'       => null,
            'last_activity_at'    => null,
            'email_notified_at'   => null,
            'email_clicked_at'    => null,
        ]);

        UserSession::truncate();
        UserSearchLog::truncate();
    }

    /**
     * Clear all activity data for a single user.
     */
    public function clearUserActivity(User $user): void
    {
        $user->update([
            'last_login_at'    => null,
            'last_activity_at' => null,
        ]);

        UserSession::forUser($user->id)->delete();
        UserSearchLog::forUser($user->id)->delete();
    }

    /**
     * Record a search/filter interaction by the user.
     * Throttled per type+value to avoid duplicates on every re-render.
     */
    public function logSearch(
        User $user,
        string $filterType,
        string $filterValue,
        ?string $pageContext = null
    ): void {
        // Deduplicate: skip if the same filter was logged in the last 30s
        $cacheKey = "search_log_{$user->id}_{$filterType}_{$filterValue}";
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return;
        }

        UserSearchLog::create([
            'user_id'      => $user->id,
            'filter_type'  => $filterType,
            'filter_value' => $filterValue,
            'page_context' => $pageContext,
            'searched_at'  => now(),
        ]);

        \Illuminate\Support\Facades\Cache::put($cacheKey, true, 30);
    }

    /**
     * Get aggregated search behaviour analytics for a user.
     *
     * Performance: all grouping, counting and relevance calculation
     * is done in SQL — zero in-memory collection processing.
     */
    public function getUserSearchStats(int $userId): array
    {
        $total = UserSearchLog::forUser($userId)->count();

        if ($total === 0) {
            return ['total_searches' => 0, 'by_type' => []];
        }

        // One query: per (filter_type, filter_value) aggregation
        $rows = UserSearchLog::query()
            ->selectRaw('
                filter_type,
                filter_value,
                COUNT(*)                        AS hit_count,
                ROUND(COUNT(*) * 100.0 / ?, 0) AS relevance,
                MAX(searched_at)                AS last_seen
            ', [$total])
            ->where('user_id', $userId)
            ->groupBy('filter_type', 'filter_value')
            ->orderBy('filter_type')
            ->orderByDesc('hit_count')
            ->get();

        // Group into types (pure collection, no extra queries)
        $byType = $rows
            ->groupBy('filter_type')
            ->map(function ($group, string $type) {
                return [
                    'type'       => $type,
                    'label'      => UserSearchLog::TYPE_LABELS[$type] ?? ucfirst($type),
                    'total_hits' => $group->sum('hit_count'),
                    'items'      => $group->map(fn ($r) => [
                        'value'     => $r->filter_value,
                        'count'     => (int) $r->hit_count,
                        'relevance' => (int) $r->relevance,
                        'last_seen' => $r->last_seen,
                    ])->values(),
                ];
            })
            ->sortByDesc('total_hits')
            ->values();

        return [
            'total_searches'   => $total,
            'by_type'          => $byType,
            'country_products' => $this->getCountryProductCorrelations($userId),
        ];
    }

    /**
     * Correlate country searches with product searches that occurred within
     * a 120-second window (same "search event").
     *
     * Uses a single self-JOIN query — no schema changes required.
     * Returns: [ 'Canada' => ['Alho', 'Pimenta'], 'Brazil' => ['Mostarda'] ]
     */
    private function getCountryProductCorrelations(int $userId): array
    {
        $rows = DB::table('user_search_logs AS c')
            ->join('user_search_logs AS p', function ($join) use ($userId) {
                $join->where('p.user_id', $userId)
                     ->where('p.filter_type', 'product')
                     ->whereRaw('ABS(TIMESTAMPDIFF(SECOND, c.searched_at, p.searched_at)) <= 120');
            })
            ->where('c.user_id', $userId)
            ->where('c.filter_type', 'country')
            ->selectRaw('c.filter_value AS country, p.filter_value AS product, COUNT(*) AS hits')
            ->groupBy('c.filter_value', 'p.filter_value')
            ->orderBy('c.filter_value')
            ->orderByDesc('hits')
            ->get();

        // Group by country → ordered product list
        return $rows
            ->groupBy('country')
            ->map(fn ($group) => $group->pluck('product')->unique()->values())
            ->toArray();
    }

    /**
     * Clear all search history for every user.
     */
    public function clearSearchHistory(): void
    {
        UserSearchLog::truncate();
    }

    /**
     * Count currently online users.
     */
    public function countOnlineUsers(): int
    {
        return User::where('last_activity_at', '>', now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES))->count();
    }

    // ─── Private Helpers ────────────────────────────────────────


    /**
     * Close a session, setting ended_at and final duration.
     */
    private function closeSession(UserSession $session): void
    {
        $session->update([
            'is_active'        => false,
            'ended_at'         => $session->last_heartbeat_at,
            'duration_seconds' => $session->started_at->diffInSeconds($session->last_heartbeat_at),
        ]);
    }

    /**
     * Close any stale (abandoned) active sessions for a user.
     */
    private function closeStaleSessionsForUser(User $user): void
    {
        UserSession::forUser($user->id)
            ->active()
            ->each(fn (UserSession $s) => $this->closeSession($s));
    }

    /**
     * Format seconds into a human-readable string.
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds === 0) {
            return '0min';
        }

        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%dh %02dmin', $hours, $minutes);
        }

        if ($minutes > 0) {
            return sprintf('%dmin', $minutes);
        }

        return $seconds . 's';
    }
}
