<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking;

use App\Contracts\ContainerTrackerInterface;
use App\Exceptions\Tracking\CarrierNotSupportedException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolves the right carrier adapter for a container number.
 *
 * Extends Laravel's Manager so drivers are created once per request and cached
 * by carrier key; `forContainer()` adds the ISO 6346 prefix resolution step.
 */
final class TrackingManager extends Manager
{
    /**
     * @param  array<string, array<string, mixed>>  $carriers
     */
    public function __construct(
        Container $container,
        private readonly ContainerPrefixResolver $resolver,
        private readonly array $carriers,
    ) {
        parent::__construct($container);
    }

    public function getDefaultDriver(): string
    {
        $configured = (string) config('tracking.default_carrier', '');

        if ($configured !== '' && isset($this->carriers[$configured])) {
            return $configured;
        }

        return (string) (array_key_first($this->carriers) ?? '');
    }

    /**
     * Resolves by ISO 6346 prefix first, then falls back to the carrier name
     * stored on the contract — real containers such as FCIU... do not carry the
     * carrier's own prefix, so the contract field is the only reliable signal.
     *
     * @throws CarrierNotSupportedException when neither signal resolves
     */
    public function forContainer(string $containerNumber, ?string $shippingCompany = null): ContainerTrackerInterface
    {
        $carrierKey = $this->resolver()->resolve($containerNumber) ?? $this->resolveByName($shippingCompany);

        if ($carrierKey === null) {
            throw new CarrierNotSupportedException($containerNumber);
        }

        $driver = $this->driver($carrierKey);

        if (! $driver instanceof ContainerTrackerInterface) {
            throw new CarrierNotSupportedException($containerNumber);
        }

        return $driver;
    }

    public function resolver(): ContainerPrefixResolver
    {
        return $this->resolver;
    }

    /**
     * Matches a free-text carrier name ("Hapag-Lloyd", "CMA CGM"…) against the
     * configured carrier codes and names.
     */
    public function resolveByName(?string $shippingCompany): ?string
    {
        $needle = $this->normalize($shippingCompany);

        if ($needle === '') {
            return null;
        }

        foreach ($this->carriers as $key => $config) {
            $code = $this->normalize((string) ($config['code'] ?? ''));
            $name = $this->normalize((string) ($config['name'] ?? ''));

            if ($needle === $code || $needle === $name || str_contains($name, $needle) || str_contains($needle, $code)) {
                return (string) $key;
            }
        }

        return null;
    }

    private function normalize(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';
    }

    /**
     * @return list<string>
     */
    public function carrierKeys(): array
    {
        return array_keys($this->carriers);
    }

    /**
     * @param  string  $driver
     * @return \App\Contracts\ContainerTrackerInterface
     */
    protected function createDriver($driver)
    {
        $config = $this->carriers[$driver] ?? null;

        if ($config === null) {
            throw new InvalidArgumentException(sprintf('Armadora [%s] não suportada.', $driver));
        }

        $class = $config['driver'] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Driver não definido para a armadora [%s].', $driver));
        }

        $instance = $class::make($config);

        if (! $instance instanceof ContainerTrackerInterface) {
            throw new InvalidArgumentException(sprintf('Driver de [%s] não implementa ContainerTrackerInterface.', $driver));
        }

        return $instance;
    }
}
