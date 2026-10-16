<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking;

/**
 * Maps an ISO 6346 container prefix (first 4 letters) to a carrier key.
 *
 * The prefix map is read from config/tracking.php so codes can be added or
 * removed without touching this class.
 */
final class ContainerPrefixResolver
{
    private const ISO6346 = '/^[A-Z]{4}[0-9]{7}$/';

    /** @var array<string, string> prefix => carrier key */
    private array $map = [];

    /**
     * @param  array<string, array<string, mixed>>  $carriers
     */
    public function __construct(array $carriers, private readonly string $iso6346 = self::ISO6346)
    {
        foreach ($carriers as $key => $config) {
            foreach (($config['prefixes'] ?? []) as $prefix) {
                $normalized = strtoupper(trim((string) $prefix));

                if ($normalized !== '') {
                    $this->map[$normalized] = (string) $key;
                }
            }
        }

        krsort($this->map);
    }

    public static function normalize(string $containerNumber): string
    {
        return strtoupper(preg_replace('/\s+/', '', $containerNumber) ?? '');
    }

    public function isValidFormat(string $containerNumber): bool
    {
        return (bool) preg_match($this->iso6346, self::normalize($containerNumber));
    }

    public function prefixOf(string $containerNumber): ?string
    {
        $normalized = self::normalize($containerNumber);

        return strlen($normalized) >= 4 ? substr($normalized, 0, 4) : null;
    }

    /**
     * Resolve the carrier key for a container, or null when unsupported.
     */
    public function resolve(string $containerNumber): ?string
    {
        $prefix = $this->prefixOf($containerNumber);

        if ($prefix === null) {
            return null;
        }

        return $this->map[$prefix] ?? null;
    }

    public function has(string $containerNumber): bool
    {
        return $this->resolve($containerNumber) !== null;
    }

    /**
     * @return array<string, string>
     */
    public function map(): array
    {
        return $this->map;
    }

    /**
     * @return list<string>
     */
    public function prefixesFor(string $carrierKey): array
    {
        $prefixes = [];

        foreach ($this->map as $prefix => $key) {
            if ($key === $carrierKey) {
                $prefixes[] = $prefix;
            }
        }

        return $prefixes;
    }

    public function count(): int
    {
        return count($this->map);
    }
}
