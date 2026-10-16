<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\TrackingDataDTO;

/**
 * Contract enforced on every carrier adapter (API, scraper or aggregator).
 *
 * Implementations must never throw for "unknown data" cases: they return a
 * TrackingDataDTO whose `state` explains what happened, so the caller can
 * render a truthful status instead of inventing one.
 */
interface ContainerTrackerInterface
{
    /**
     * Resolve a container number to normalized tracking data.
     *
     * @param  array<string, mixed>  $context  real contract facts (ETD, ETA, countries,
     *                                         contract date, status) used when the backend
     *                                         has no live source available
     *
     * @throws \App\Exceptions\Tracking\CarrierApiException when the carrier backend fails
     * @throws \App\Exceptions\Tracking\ContainerNotFoundException when the carrier has no record
     */
    public function track(string $containerNumber, array $context = []): TrackingDataDTO;

    /**
     * Human readable carrier name, e.g. "Maersk".
     */
    public function carrierName(): string;

    /**
     * Lowercase carrier key used by the config, e.g. "maersk".
     */
    public function carrierCode(): string;
}
