<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail of every submission of the public registration form.
 *
 * The e-mail is stored only as a hash so the table can be queried for abuse
 * ("this address submitted 200 times today") without keeping PII around.
 */
class ExternalRegistrationLog extends Model
{
    public const OUTCOME_CREATED = 'created';

    public const OUTCOME_DUPLICATE = 'duplicate';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_FAILED = 'failed';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'email_hash',
        'ip',
        'origin',
        'user_agent',
        'outcome',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $email,
        string $outcome,
        ?string $reason = null,
        ?int $userId = null,
    ): void {
        self::create([
            'user_id' => $userId,
            'email_hash' => hash('sha256', strtolower(trim($email))),
            'ip' => request()->ip(),
            'origin' => self::currentOrigin(),
            'user_agent' => self::currentUserAgent(),
            'outcome' => $outcome,
            'reason' => $reason,
        ]);
    }

    private static function currentOrigin(): ?string
    {
        $origin = request()->headers->get('Origin');

        if (is_string($origin) && $origin !== '' && $origin !== 'null') {
            return substr($origin, 0, 255);
        }

        $referer = request()->headers->get('Referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host) ? substr($host, 0, 255) : null;
    }

    private static function currentUserAgent(): ?string
    {
        $agent = request()->userAgent();

        return is_string($agent) && $agent !== '' ? substr($agent, 0, 255) : null;
    }
}
