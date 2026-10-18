<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Rate limiter keys for the authentication routes.
 *
 * Centralised so the limiter and anything that needs to inspect or clear a
 * bucket (tests, the login controller) can never drift apart.
 */
final class ThrottleKeys
{
    public static function loginByIp(string $ip): string
    {
        return 'auth|login|ip|'.$ip;
    }

    public static function registerByIp(string $ip): string
    {
        return 'auth|register|ip|'.$ip;
    }

    public static function registerDayByIp(string $ip): string
    {
        return 'auth|register|day|ip|'.$ip;
    }

    public static function registerByEmail(string $email): string
    {
        return 'auth|register|email|'.self::digest($email);
    }

    public static function confirmByIp(string $ip): string
    {
        return 'auth|confirm|ip|'.$ip;
    }

    public static function digest(string $email): string
    {
        return sha1(Str::lower(trim($email)));
    }
}
