<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Result of a reCAPTCHA assessment.
 *
 * `unreachable` deliberately counts as a pass: Google being down must not lock
 * every human out of the platform. The request budgets already in place remain
 * the safety net, and the incident is logged for operations.
 */
final class RecaptchaOutcome
{
    private function __construct(
        public readonly bool $passed,
        public readonly string $reason,
        public readonly ?float $score = null,
    ) {
    }

    public static function disabled(): self
    {
        return new self(true, 'disabled');
    }

    public static function passed(?float $score): self
    {
        return new self(true, 'passed', $score);
    }

    public static function unreachable(): self
    {
        return new self(true, 'unreachable');
    }

    public static function failed(string $reason, ?float $score = null): self
    {
        return new self(false, $reason, $score);
    }
}
