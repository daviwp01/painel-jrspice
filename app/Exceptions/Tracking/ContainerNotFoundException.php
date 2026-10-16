<?php

declare(strict_types=1);

namespace App\Exceptions\Tracking;

final class ContainerNotFoundException extends TrackingException
{
    public function __construct(string $containerNumber, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('A transportadora não possui registro para o container %s.', $containerNumber),
            'not_found',
            0,
            $previous,
        );
    }
}
