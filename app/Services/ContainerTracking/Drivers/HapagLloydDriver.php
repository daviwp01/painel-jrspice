<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\Drivers;

/**
 * Hapag-Lloyd adapter.
 *
 * Endpoint, credentials and payload mapping live in config/tracking.php so this
 * class only owns carrier identity; add carrier specific behaviour by
 * overriding the protected backend methods when a portal needs custom parsing.
 */
final class HapagLloydDriver extends AbstractCarrierDriver
{
    public function carrierName(): string
    {
        return 'Hapag-Lloyd';
    }

    public function carrierCode(): string
    {
        return 'hapag_lloyd';
    }
}
