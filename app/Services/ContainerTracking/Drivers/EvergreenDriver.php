<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\Drivers;

/**
 * Evergreen Marine adapter.
 *
 * Endpoint, credentials and payload mapping live in config/tracking.php so this
 * class only owns carrier identity; add carrier specific behaviour by
 * overriding the protected backend methods when a portal needs custom parsing.
 */
final class EvergreenDriver extends AbstractCarrierDriver
{
    public function carrierName(): string
    {
        return 'Evergreen Marine';
    }

    public function carrierCode(): string
    {
        return 'evergreen';
    }
}
