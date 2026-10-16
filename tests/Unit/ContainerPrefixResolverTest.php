<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\Tracking\CarrierNotSupportedException;
use App\Services\ContainerTracking\ContainerPrefixResolver;
use App\Services\ContainerTracking\TrackingManager;
use PHPUnit\Framework\TestCase;

class ContainerPrefixResolverTest extends TestCase
{
    /**
     * The nine mandated prefixes from the specification.
     *
     * @return array<int, array{string, string}>
     */
    public static function mandatedPrefixes(): array
    {
        return [
            ['MSCU', 'msc'],
            ['MAEU', 'maersk'],
            ['MSKU', 'maersk'],
            ['EGLV', 'evergreen'],
            ['CMAU', 'cma_cgm'],
            ['AMCU', 'cma_cgm'],
            ['HLCU', 'hapag_lloyd'],
            ['HDMU', 'hmm'],
            ['OOLU', 'oocl'],
            ['COSU', 'cosco'],
            ['ZIMU', 'zim'],
        ];
    }

    private function resolver(): ContainerPrefixResolver
    {
        $config = require dirname(__DIR__, 2).'/config/tracking.php';

        return new ContainerPrefixResolver($config['carriers']);
    }

    public function test_resolves_every_mandated_prefix(): void
    {
        $resolver = $this->resolver();

        foreach (self::mandatedPrefixes() as [$prefix, $carrier]) {
            $this->assertSame($carrier, $resolver->resolve($prefix.'0000000'), "prefixo {$prefix}");
        }
    }

    public function test_maps_more_than_thirty_prefixes(): void
    {
        $this->assertGreaterThanOrEqual(30, $this->resolver()->count());
    }

    public function test_normalizes_case_and_spacing_before_resolving(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('maersk', $resolver->resolve('  maeu 123456 7'));
        $this->assertSame('MAEU1234567', ContainerPrefixResolver::normalize(' maeu 1234567 '));
    }

    public function test_rejects_unknown_prefix(): void
    {
        $resolver = $this->resolver();

        $this->assertNull($resolver->resolve('ZZZZ1234567'));
        $this->assertFalse($resolver->has('ZZZZ1234567'));
    }

    public function test_validates_iso_6346_format(): void
    {
        $resolver = $this->resolver();

        $this->assertTrue($resolver->isValidFormat('MSCU1234567'));
        $this->assertFalse($resolver->isValidFormat('MSC1234567'), 'precisa de 4 letras');
        $this->assertFalse($resolver->isValidFormat('MSCU123456'), 'precisa de 7 dígitos');
        // a normalização eleva para caixa alta antes de validar
        $this->assertTrue($resolver->isValidFormat('mscu1234567'));
        // espaços internos são removidos (ex.: "MSCU 1234567" colado no formulário)
        $this->assertTrue($resolver->isValidFormat('MSCU 1234567'));
        $this->assertFalse($resolver->isValidFormat(''), 'não pode ser vazio');
    }

    public function test_prefix_of_returns_the_first_four_letters(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('MSCU', $resolver->prefixOf('MSCU1234567'));
        $this->assertNull($resolver->prefixOf('MS'));
    }

    public function test_manager_rejects_unsupported_carrier(): void
    {
        $config = require dirname(__DIR__, 2).'/config/tracking.php';
        $resolver = new ContainerPrefixResolver($config['carriers']);

        $container = $this->createStub(\Illuminate\Contracts\Container\Container::class);

        $manager = new TrackingManager($container, $resolver, $config['carriers']);

        $this->expectException(CarrierNotSupportedException::class);
        $manager->forContainer('ZZZZ1234567');
    }

    public function test_manager_returns_the_driver_matching_the_prefix(): void
    {
        $config = require dirname(__DIR__, 2).'/config/tracking.php';
        $resolver = new ContainerPrefixResolver($config['carriers']);
        $container = $this->createStub(\Illuminate\Contracts\Container\Container::class);

        $manager = new TrackingManager($container, $resolver, $config['carriers']);

        $driver = $manager->forContainer('OOLU9876543');

        $this->assertSame('oocl', $driver->carrierCode());
        $this->assertSame('OOCL', $driver->carrierName());
    }

    public function test_manager_exposes_the_carrier_catalogue(): void
    {
        $config = require dirname(__DIR__, 2).'/config/tracking.php';
        $resolver = new ContainerPrefixResolver($config['carriers']);
        $manager = new TrackingManager(
            $this->createStub(\Illuminate\Contracts\Container\Container::class),
            $resolver,
            $config['carriers'],
        );

        $this->assertSame(
            ['hmm', 'evergreen', 'maersk', 'cma_cgm', 'hapag_lloyd', 'msc', 'oocl', 'cosco', 'zim'],
            $manager->carrierKeys(),
        );
        $this->assertCount(9, $manager->carrierKeys());
    }
}
