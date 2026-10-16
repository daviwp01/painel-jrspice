<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\Drivers;

/**
 * CMA CGM adapter.
 *
 * Endpoint, credentials and payload mapping live in config/tracking.php so this
 * class only owns carrier identity; add carrier specific behaviour by
 * overriding the protected backend methods when a portal needs custom parsing.
 */
final class CmaCgmDriver extends AbstractCarrierDriver
{
    public function carrierName(): string
    {
        return 'CMA CGM';
    }

    public function carrierCode(): string
    {
        return 'cma_cgm';
    }
}
