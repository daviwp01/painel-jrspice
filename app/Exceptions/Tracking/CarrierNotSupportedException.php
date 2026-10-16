<?php

declare(strict_types=1);

namespace App\Exceptions\Tracking;

final class CarrierNotSupportedException extends TrackingException
{
    public function __construct(string $containerNumber, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Prefixo do container %s não corresponde a nenhuma das armadoras suportadas.',
                $containerNumber,
            ),
            'unsupported',
            0,
            $previous,
        );
    }
}
