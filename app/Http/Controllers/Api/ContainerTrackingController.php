<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ContainerTrackerInterface;
use App\DataTransferObjects\TrackingDataDTO;
use App\Exceptions\Tracking\TrackingException;
use App\Http\Controllers\Concerns\AuthorizesContractAccess;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\ContainerTracking\ContainerPrefixResolver;
use App\Services\ContainerTracking\TrackingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Track & Trace endpoints consumed by the client portal.
 *
 * Responses always carry the normalized TrackingDataDTO envelope plus a `state`
 * so the front-end can render a truthful status instead of guessing.
 */
class ContainerTrackingController extends Controller
{
    use AuthorizesContractAccess;

    public function __construct(
        private readonly TrackingManager $manager,
        private readonly ContainerPrefixResolver $resolver,
    ) {}

    /**
     * GET /rastreamento/{container}
     */
    public function show(string $container): JsonResponse
    {
        $container = ContainerPrefixResolver::normalize($container);

        if (! $this->resolver->isValidFormat($container)) {
            return response()->json([
                'container_number' => $container,
                'carrier_name' => null,
                'carrier_code' => null,
                'status' => 'UNKNOWN',
                'state' => TrackingDataDTO::STATE_ERROR,
                'origin' => [],
                'destination' => [],
                'vessel' => [],
                'events' => [],
                'last_updated' => null,
                'message' => 'Número de container inválido. Formato esperado: 4 letras + 7 dígitos (ISO 6346), ex.: MSCU1234567.',
                'provider' => null,
            ], 422);
        }

        return response()->json($this->track($container));
    }

    /**
     * GET /meus-produtos/{contract}/rastreamento
     *
     * Resolves the container from a contract the caller is allowed to see, so
     * the portal never posts an arbitrary container number.
     */
    public function forContract(Contract $contract): JsonResponse
    {
        $this->authorizeContractAccess($contract);
        $contract->loadMissing(['exporter:id,country', 'importer:id,country']);

        $container = $contract->container_number;

        if ($container === null || trim($container) === '') {
            return response()->json($this->envelope(
                containerNumber: '',
                state: 'unavailable',
                message: 'Este contrato ainda não possui número de container informado.',
            ));
        }

        $container = ContainerPrefixResolver::normalize($container);

        if (! $this->resolver->isValidFormat($container)) {
            return response()->json($this->envelope(
                containerNumber: $container,
                state: TrackingDataDTO::STATE_ERROR,
                message: 'O número de container cadastrado não segue o padrão ISO 6346 (4 letras + 7 dígitos).',
            ));
        }

        return response()->json($this->track($container, $contract));
    }

    /**
     * @return array<string, mixed>
     */
    private function track(string $container, ?Contract $contract = null): array
    {
        try {
            $driver = $this->manager->forContainer($container, $contract?->shipping_company);
        } catch (TrackingException $exception) {
            return $this->envelope($container, $exception->state, $exception->getMessage());
        }

        $cache = Cache::store(config('tracking.cache.store'));
        $cacheKey = sprintf('tracking.container.%s.%s', $driver->carrierCode(), md5($container));

        $cached = $cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $dto = $driver->track($container, $this->contractContext($contract));
        } catch (TrackingException $exception) {
            return $this->envelope($container, $exception->state, $exception->getMessage(), $driver);
        }

        $payload = $dto->toArray();

        // Only real, successful lookups are worth caching: a transient error or a
        // missing credential must be retried on the next request, and demo data
        // must disappear as soon as the flag is turned off.
        if ($dto->isSuccess() && $dto->provider !== 'demonstracao') {
            $cache->put($cacheKey, $payload, (int) config('tracking.cache.ttl', 60));
        }

        return $payload;
    }

    /**
     * Real contract facts handed to the driver so it never has to invent them.
     *
     * @return array<string, mixed>
     */
    private function contractContext(?Contract $contract): array
    {
        if ($contract === null) {
            return [];
        }

        return [
            'origin_country' => $contract->exporter?->country ?? $contract->country,
            'destination_country' => $contract->importer?->country ?? $contract->country,
            'contract_date' => $contract->date?->toDateString(),
            'etd' => $contract->etd_date?->toDateString(),
            'eta' => $contract->eta_date?->toDateString(),
            'finalized' => $contract->is_finished,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(
        string $containerNumber,
        string $state,
        string $message,
        ?ContainerTrackerInterface $driver = null,
    ): array {
        return (new TrackingDataDTO(
            containerNumber: $containerNumber,
            carrierName: $driver?->carrierName() ?? '',
            carrierCode: $driver?->carrierCode() ?? '',
            status: 'UNKNOWN',
            state: $state,
            message: $message,
        ))->toArray();
    }
}
