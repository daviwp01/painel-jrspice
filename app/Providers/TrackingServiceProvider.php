<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ContainerTracking\ContainerPrefixResolver;
use App\Services\ContainerTracking\TrackingManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ContainerPrefixResolver::class,
            static fn (Application $app): ContainerPrefixResolver => new ContainerPrefixResolver(
                (array) $app['config']->get('tracking.carriers', []),
                (string) $app['config']->get('tracking.iso6346'),
            ),
        );

        $this->app->singleton(
            TrackingManager::class,
            static fn (Application $app): TrackingManager => new TrackingManager(
                $app,
                $app->make(ContainerPrefixResolver::class),
                (array) $app['config']->get('tracking.carriers', []),
            ),
        );

        $this->app->alias(TrackingManager::class, 'tracking');
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [ContainerPrefixResolver::class, TrackingManager::class, 'tracking'];
    }
}
