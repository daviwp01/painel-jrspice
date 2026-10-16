<?php

declare(strict_types=1);

use App\Services\ContainerTracking\Drivers\CmaCgmDriver;
use App\Services\ContainerTracking\Drivers\CoscoDriver;
use App\Services\ContainerTracking\Drivers\EvergreenDriver;
use App\Services\ContainerTracking\Drivers\HapagLloydDriver;
use App\Services\ContainerTracking\Drivers\HmmDriver;
use App\Services\ContainerTracking\Drivers\MaerskDriver;
use App\Services\ContainerTracking\Drivers\MscDriver;
use App\Services\ContainerTracking\Drivers\OoclDriver;
use App\Services\ContainerTracking\Drivers\ZimDriver;

/*
|--------------------------------------------------------------------------
| Multi-carrier container tracking
|--------------------------------------------------------------------------
|
| `prefixes` is the ISO 6346 owner code map used by ContainerPrefixResolver.
| It is deliberately editable here instead of being hard-coded: add or remove
| codes without touching PHP. Validate codes against the public BIC register
| before enabling them in production.
|
| `backend` selects how a carrier is reached:
|   api        -> direct call to the official API / DCSA endpoint (needs url + credentials)
|   aggregator -> a paid third-party aggregator (needs TRACKING_AGGREGATOR_URL)
|   scraper    -> the carrier public portal (needs url + CSS selectors)
|
| When the selected backend has no credentials in .env the driver returns a
| `not_configured` state instead of inventing data.
|
*/

$defaultMap = [
    'status' => 'status',
    'origin.port' => 'origin.port',
    'origin.country' => 'origin.country',
    'origin.departure' => 'origin.departure',
    'destination.port' => 'destination.port',
    'destination.country' => 'destination.country',
    'destination.eta' => 'destination.eta',
    'vessel.name' => 'vessel.name',
    'vessel.voyage' => 'vessel.voyage',
    'last_updated' => 'last_updated',
    'events' => 'events',
    'events.date' => 'date',
    'events.location' => 'location',
    'events.description' => 'description',
    'events.status_code' => 'status_code',
];

$carrierDefaults = [
    'backend' => null,
    'api' => [
        'url' => null,
        'method' => 'GET',
        'headers' => [],
        'query' => [],
        'body' => [],
        'map' => $defaultMap,
        'timeout' => 15,
    ],
    'scraper' => [
        'url' => null,
        'method' => 'GET',
        'body' => [],
        'selectors' => [
            'status' => null,
            'origin.port' => null,
            'origin.country' => null,
            'origin.departure' => null,
            'destination.port' => null,
            'destination.country' => null,
            'destination.eta' => null,
            'vessel.name' => null,
            'vessel.voyage' => null,
            'last_updated' => null,
            'events' => null,
            'events.date' => null,
            'events.location' => null,
            'events.description' => null,
            'events.status_code' => null,
        ],
        'timeout' => 20,
    ],
];

return [

    'iso6346' => '/^[A-Z]{4}[0-9]{7}$/',

    'default_backend' => env('TRACKING_DEFAULT_BACKEND', 'api'),

    // Used only when the manager is asked for a driver without a carrier key.
    'default_carrier' => env('TRACKING_DEFAULT_CARRIER', 'maersk'),

    // Real tracking snapshots captured by hand from the carrier portals,
    // keyed by container number ({SNAPSHOT_PATH}/{CONTAINER}.json).
    // Relative or absolute; resolved against database_path() at runtime so the
    // config file never depends on a booted application (unit tests).
    'snapshot_path' => env('TRACKING_SNAPSHOT_PATH'),


    'cache' => [
        'store' => env('TRACKING_CACHE_STORE'),
        'ttl' => (int) env('TRACKING_CACHE_TTL', 60),
    ],

    'http' => [
        'timeout' => (int) env('TRACKING_HTTP_TIMEOUT', 15),
        'connect_timeout' => (int) env('TRACKING_HTTP_CONNECT_TIMEOUT', 5),
    ],

    // Shared credentials used when a carrier runs through a paid aggregator.
    'aggregator' => [
        'driver' => 'terminal49',
        'url' => env('TRACKING_AGGREGATOR_URL', 'https://api.terminal49.com/v2'),
        'api_key' => env('TRACKING_AGGREGATOR_KEY'),
        'headers' => [
            'Authorization' => 'Token '.env('TRACKING_AGGREGATOR_KEY'),
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ],
        // JSON:API contract validated against the live API in 2026-10-16.
        'create' => [
            'path' => '/tracking_requests',
            'type' => 'tracking_request',
            'request_type' => 'container',
        ],
        'reads' => [
            ['path' => '/tracking_requests/{id}', 'id_at' => 'data.id', 'pick' => 'data.relationships.container.data.id', 'as' => 'container_id'],
            ['path' => '/containers/{container_id}', 'pick' => 'data.id', 'as' => 'container_id'],
            ['path' => '/containers/{container_id}/transport_events', 'as' => 'events'],
        ],
        'map' => $defaultMap,
    ],

    'carriers' => [

        'hmm' => array_merge($carrierDefaults, [
            'scac' => 'HDMU',
            'backend' => 'aggregator',
            'name' => 'HMM (Hyundai Merchant Marine)',
            'code' => 'hmm',
            'driver' => HmmDriver::class,
            'prefixes' => ['HDMU', 'HMMU'],
            'api' => ['url' => env('TRACKING_HMM_URL')],
            'scraper' => ['url' => env('TRACKING_HMM_URL')],
        ]),

        // Portal público verificado em 2026-10-16: formulário server-side
        // (formCargo -> POST /servlet/TDB1_CargoTracking.do) com SEL=m_cntr
        // ("por container") e o campo CNTR. Sem JavaScript, portanto utilizável
        // pelo scraper com Http + DomCrawler.
        'evergreen' => array_merge($carrierDefaults, [
            'scac' => 'EGLV',
            'backend' => 'aggregator',
            'name' => 'Evergreen Marine',
            'code' => 'evergreen',
            'driver' => EvergreenDriver::class,
            'prefixes' => ['EGLV', 'EGHU', 'EGSU'],
            'api' => ['url' => env('TRACKING_EVERGREEN_URL')],
            'scraper' => [
                'url' => env('TRACKING_EVERGREEN_URL', 'https://www.shipmentlink.com/servlet/TDB1_CargoTracking.do'),
                'method' => 'POST',
                'body' => [
                    'SEL' => 'm_cntr',
                    'CNTR' => '{container}',
                    'BL' => '',
                    'bkno' => '',
                ],
                'selectors' => [
                    'status' => null,
                    'origin.port' => null,
                    'origin.country' => null,
                    'origin.departure' => null,
                    'destination.port' => null,
                    'destination.country' => null,
                    'destination.eta' => null,
                    'vessel.name' => null,
                    'vessel.voyage' => null,
                    'last_updated' => null,
                    'events' => null,
                    'events.date' => null,
                    'events.location' => null,
                    'events.description' => null,
                    'events.status_code' => null,
                ],
                'timeout' => 20,
            ],
        ]),

        'maersk' => array_merge($carrierDefaults, [
            'scac' => 'MAEU',
            'backend' => 'aggregator',
            'name' => 'Maersk',
            'code' => 'maersk',
            'driver' => MaerskDriver::class,
            'prefixes' => ['MAEU', 'MSKU', 'MRSU', 'MRKU', 'MCIU', 'MSDU'],
            'api' => [
                'url' => env('TRACKING_MAERSK_URL'),
                'headers' => ['Authorization' => 'Bearer '.env('TRACKING_MAERSK_KEY')],
            ],
            'scraper' => ['url' => env('TRACKING_MAERSK_URL')],
        ]),

        'cma_cgm' => array_merge($carrierDefaults, [
            'scac' => 'CMDU',
            'backend' => 'aggregator',
            'name' => 'CMA CGM',
            'code' => 'cma_cgm',
            'driver' => CmaCgmDriver::class,
            'prefixes' => ['CMAU', 'AMCU', 'CGMU', 'CMDU', 'CLHU'],
            'api' => [
                'url' => env('TRACKING_CMACGM_URL'),
                'headers' => ['Authorization' => 'Bearer '.env('TRACKING_CMACGM_KEY')],
            ],
            'scraper' => ['url' => env('TRACKING_CMACGM_URL')],
        ]),

        'hapag_lloyd' => array_merge($carrierDefaults, [
            'scac' => 'HLCU',
            'backend' => 'aggregator',
            'name' => 'Hapag-Lloyd',
            'code' => 'hapag_lloyd',
            'driver' => HapagLloydDriver::class,
            'prefixes' => ['HLCU', 'HLXU', 'HCAU'],
            'api' => [
                'url' => env('TRACKING_HAPAG_URL'),
                'headers' => ['Authorization' => 'Bearer '.env('TRACKING_HAPAG_KEY')],
            ],
            'scraper' => ['url' => env('TRACKING_HAPAG_URL')],
        ]),

        'msc' => array_merge($carrierDefaults, [
            'scac' => 'MSCU',
            'backend' => 'aggregator',
            'name' => 'MSC (Mediterranean Shipping Company)',
            'code' => 'msc',
            'driver' => MscDriver::class,
            'prefixes' => ['MSCU', 'MEDU', 'MEGU'],
            'api' => [
                'url' => env('TRACKING_MSC_URL'),
                'headers' => ['Authorization' => 'Bearer '.env('TRACKING_MSC_KEY')],
            ],
            'scraper' => ['url' => env('TRACKING_MSC_URL')],
        ]),

        'oocl' => array_merge($carrierDefaults, [
            'scac' => 'OOLU',
            'backend' => 'aggregator',
            'name' => 'OOCL',
            'code' => 'oocl',
            'driver' => OoclDriver::class,
            'prefixes' => ['OOLU', 'OOCU'],
            'api' => ['url' => env('TRACKING_OOCL_URL')],
            'scraper' => ['url' => env('TRACKING_OOCL_URL')],
        ]),

        'cosco' => array_merge($carrierDefaults, [
            'scac' => 'COSU',
            'backend' => 'aggregator',
            'name' => 'Cosco Shipping',
            'code' => 'cosco',
            'driver' => CoscoDriver::class,
            'prefixes' => ['COSU', 'CSNU', 'CCLU', 'CBHU', 'CNCU'],
            'api' => ['url' => env('TRACKING_COSCO_URL')],
            'scraper' => ['url' => env('TRACKING_COSCO_URL')],
        ]),

        'zim' => array_merge($carrierDefaults, [
            'scac' => 'ZIMU',
            'backend' => 'aggregator',
            'name' => 'Zim Integrated Shipping',
            'code' => 'zim',
            'driver' => ZimDriver::class,
            'prefixes' => ['ZIMU', 'ZAXU', 'ZCSU'],
            'api' => ['url' => env('TRACKING_ZIM_URL')],
            'scraper' => ['url' => env('TRACKING_ZIM_URL')],
        ]),

    ],
];
