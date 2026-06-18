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
        ?string $pageContext = null,
        bool $isRehydrating = false
    ): void {
        if ($isRehydrating) {
            // Se o frontend está reidratando a memória (ex: Chile), removemos o log "padrão" (ex: China) 
            // que o Backend acabou de criar acidentalmente há poucos segundos atrás.
            UserSearchLog::where('user_id', $user->id)
                ->where('filter_type', $filterType)
                ->where('searched_at', '>=', now()->subSeconds(10))
                ->delete();
                
            // Limpamos o cache e a sessão para não impedir a gravação do valor real da memória
            \Illuminate\Support\Facades\Cache::forget("search_log_{$user->id}_{$filterType}_{$filterValue}");
            session()->forget("last_search_{$filterType}");
        }

        // 1. Evita requisições concorrentes e cliques duplos (trava atômica de 2 segundos)
        $cacheKey = "search_log_{$user->id}_{$filterType}_{$filterValue}";
        if (!\Illuminate\Support\Facades\Cache::add($cacheKey, true, 2)) {
            return; // Se já existir no cache (adicionado por outra requisição simultânea), bloqueia.
        }

        // 2. Evita spam de F5/Recarregamento de página ou troca de abas
        // Só registra se o usuário realmente MUDOU o filtro globalmente
        if ($filterType !== 'export') {
            $sessionKey = "last_search_{$filterType}";
            if (session()->get($sessionKey) === (string) $filterValue) {
                return;
            }
            session()->put($sessionKey, (string) $filterValue);
        }

        UserSearchLog::create([
            'user_id'      => $user->id,
            'filter_type'  => $filterType,
            'filter_value' => $filterValue,
            'page_context' => $pageContext,
            'searched_at'  => now(),
        ]);
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
            ->where('filter_type', '!=', 'export')
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
            'engagement'       => $this->getEngagementStats($userId),
        ];
    }

    /**
     * Calcula as métricas de engajamento do usuário.
     */
    private function getEngagementStats(int $userId): array
    {
        $last30Days = now()->subDays(30);

        // Fetch session starts for the last 30 days to calculate metrics in memory
        // This is database-agnostic and very fast for a single user's last 30 days
        $recentSessions = DB::table('user_sessions')
            ->where('user_id', $userId)
            ->where('started_at', '>=', $last30Days)
            ->pluck('started_at')
            ->map(fn($date) => \Carbon\Carbon::parse($date));

        // 1. Dias Ativos nos últimos 30 dias
        $activeDays = $recentSessions->map->toDateString()->unique()->count();

        // 2. Horário de Pico
        $hourCounts = $recentSessions->countBy(fn($d) => $d->hour);
        $peakHour = $hourCounts->isEmpty() ? null : $hourCounts->sortDesc()->keys()->first();
        
        $peakHourLabel = 'N/A';
        if ($peakHour !== null) {
            $nextHour = ($peakHour + 1) % 24;
            $peakHourLabel = str_pad($peakHour, 2, '0', STR_PAD_LEFT) . ':00 - ' . str_pad($nextHour, 2, '0', STR_PAD_LEFT) . ':00';
        }

        // 3. Dia da Semana Favorito
        $dayNames = [
            0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira',
            3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'
        ];
        $dayCounts = $recentSessions->countBy(fn($d) => $d->dayOfWeek);
        $favDayId = $dayCounts->isEmpty() ? null : $dayCounts->sortDesc()->keys()->first();
        $favDayLabel = $favDayId !== null ? $dayNames[$favDayId] : 'N/A';

        // 4. Total de Exportações
        $exportLogs = UserSearchLog::forUser($userId)->where('filter_type', 'export')->get();
        $exportsCount = $exportLogs->count();
        
        // Pega os últimos itens baixados, remove "PDF: " para mostrar os nomes de forma limpa
        $exportedItems = $exportLogs->sortByDesc('searched_at')
            ->pluck('filter_value')
            ->map(fn($val) => str_replace('PDF: ', '', $val))
            ->unique()
            ->values()
            ->take(5) // Mostra os 5 itens mais recentes
            ->toArray();

        // 5. Health Score (0 - 100)
        // Fórmula simples: cada dia ativo vale 4 pontos (max 120, mas capado em 100), e cada export vale 5 pontos extras
        $healthScore = min(100, ($activeDays * 4) + ($exportsCount * 5));

        return [
            'active_days_last_30' => $activeDays,
            'peak_hour'           => $peakHourLabel,
            'favorite_day'        => $favDayLabel,
            'total_exports'       => $exportsCount,
            'exported_items'      => $exportedItems,
            'health_score'        => $healthScore,
        ];
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
