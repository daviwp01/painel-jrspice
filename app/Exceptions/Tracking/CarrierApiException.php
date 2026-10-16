<?php

declare(strict_types=1);

namespace App\Exceptions\Tracking;

final class CarrierApiException extends TrackingException
{
    public function __construct(
        string $message,
        string $state = 'error',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $state, 0, $previous);
    }
}
