<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Encryption\MissingAppKeyException;

/**
 * Short-lived, signed marker embedded in the public registration form.
 *
 * It is not a secret — anyone can read it from the page — but a client that
 * does not fetch the form first cannot produce a valid one, which stops the
 * cheapest class of scripted abuse. Combined with the honeypot and the rate
 * limiter it forms the three independent layers of the endpoint.
 */
final class EmbedToken
{
    public const PAYLOAD_SEPARATOR = '|';

    /**
     * @throws MissingAppKeyException
     */
    public static function issue(): string
    {
        $issuedAt = time();

        return self::PAYLOAD_SEPARATOR.$issuedAt.'|'.self::signature($issuedAt);
    }

    public static function isValid(?string $token, ?int $minFillSeconds = null, ?int $ttlSeconds = null): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $parts = explode(self::PAYLOAD_SEPARATOR, $token);

        if (count($parts) !== 3 || $parts[0] !== '') {
            return false;
        }

        if (! ctype_digit($parts[1])) {
            return false;
        }

        $issuedAt = (int) $parts[1];

        if (! hash_equals(self::signature($issuedAt), $parts[2])) {
            return false;
        }

        $ttl = $ttlSeconds ?? (int) config('embed.token_ttl_seconds');
        $now = time();

        if ($issuedAt > $now + 60) {
            return false;
        }

        if ($now - $issuedAt > $ttl) {
            return false;
        }

        $minFill = $minFillSeconds ?? (int) config('embed.min_fill_seconds');

        if ($minFill > 0 && $now - $issuedAt < $minFill) {
            return false;
        }

        return true;
    }

    private static function signature(int $issuedAt): string
    {
        return hash_hmac(
            'sha256',
            'external-register'.self::PAYLOAD_SEPARATOR.$issuedAt,
            (string) config('app.key'),
        );
    }
}
