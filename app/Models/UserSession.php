<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'started_at',
        'last_heartbeat_at',
        'ended_at',
        'duration_seconds',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'started_at'        => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'ended_at'          => 'datetime',
            'is_active'         => 'boolean',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ─── Accessors ──────────────────────────────────────────────

    /**
     * Human-readable duration string.
     */
    public function getFormattedDurationAttribute(): string
    {
        $seconds = $this->duration_seconds;

        if ($seconds < 60) {
            return $seconds . 's';
        }

        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%dh %02dmin', $hours, $minutes);
        }

        return sprintf('%dmin', $minutes);
    }

    /**
     * Simplified browser name extracted from user-agent.
     */
    public function getBrowserAttribute(): string
    {
        $ua = $this->user_agent ?? '';

        if (str_contains($ua, 'Edg'))     return 'Edge';
        if (str_contains($ua, 'OPR'))     return 'Opera';
        if (str_contains($ua, 'Chrome'))   return 'Chrome';
        if (str_contains($ua, 'Firefox'))  return 'Firefox';
        if (str_contains($ua, 'Safari'))   return 'Safari';

        return 'Other';
    }
}
