<?php

declare(strict_types=1);

namespace App\Exceptions\Tracking;

use RuntimeException;

/**
 * Base type for every failure raised by the container tracking module.
 */
abstract class TrackingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $state = 'error',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
