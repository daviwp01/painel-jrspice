<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Tracking\CarrierApiException;
use App\Models\Setting;
use App\Services\ContainerTracking\TimeToCargo\TimeToCargoArrivalExtractor;
use App\Services\ContainerTracking\TimeToCargo\TimeToCargoClient;
use App\Services\ContainerTracking\Tracktainer\ContractArrivalSyncService;
use App\Services\ContainerTracking\Tracktainer\TracktainerClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Writes arrival dates onto contracts (contracts.eta_date) from the active
 * aggregator.
 *
 * TimeToCargo (active): GET /v2/shipments pages with nested tracking data —
 * no watermark needed (idempotent no-op when eta_date already matches).
 * Webhooks are the real-time path; this command is the safety net.
 *
 * Tracktainer (standby): filter[updated_after] watermark in settings.
 */
class SyncContractArrivals extends Command
{
    private const WATERMARK_KEY = 'tracktainer_sync_after';

    private const LOOKBACK_ON_FIRST_RUN_DAYS = 30;

    private const MAX_PAGES = 50;

    protected $signature = 'tracking:sync-arrivals {--dry-run : List what would change without writing}';

    protected $description = 'Sincroniza contracts.eta_date com a chegada real do agregador ativo (TimeToCargo / Tracktainer)';

    public function handle(ContractArrivalSyncService $sync, TimeToCargoArrivalExtractor $extractor): int
    {
        $driver = (string) config('tracking.aggregator.driver', '');

        return match ($driver) {
            'timetocargo' => $this->pollTimeToCargo($sync, $extractor),
            'tracktainer' => $this->pollTracktainer($sync),
            default => $this->skip($driver),
        };
    }

    private function skip(string $driver): int
    {
        $this->info(sprintf(
            'Agregador ativo é [%s] — nenhum sync de chegadas configurado para este driver. Nada a fazer.',
            $driver !== '' ? $driver : 'desconhecido',
        ));

        return self::SUCCESS;
    }

    private function pollTracktainer(ContractArrivalSyncService $sync): int
    {
        $client = TracktainerClient::fromConfig();

        if (! $client->isConfigured()) {
            $this->warn('Tracktainer não configurado (TRACKING_AGGREGATOR_URL / _KEY). Nada a fazer.');

            return self::SUCCESS;
        }

        $watermark = $this->watermark();
        $runStartedAt = now('UTC');
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $updated = [];
        $page = 1;
        $truncated = false;

        $this->info(sprintf('Polling Tracktainer shipments updated since %s…', $watermark->toIso8601String()));

        try {
            do {
                $result = $client->listShipments([
                    'filter[updated_after]' => $watermark->toIso8601String(),
                ], $page);

                foreach ($result['data'] as $row) {
                    $checked++;
                    $attributes = (array) ($row['attributes'] ?? []);

                    foreach ($sync->syncShipment($attributes, persist: ! $dryRun) as $change) {
                        $updated[] = $change;
                        $this->line(sprintf(
                            '  contrato #%d (%s) eta_date → %s',
                            $change['contract_id'],
                            $change['container'],
                            $change['eta_date'],
                        ));
                    }
                }

                $next = $result['links']['next'] ?? null;
                $hasMorePage = is_string($next) && $next !== '';
                $page++;

                if ($hasMorePage && $page >= self::MAX_PAGES) {
                    $truncated = true;
                    break;
                }
            } while (! empty($result['data']) && $hasMorePage);
        } catch (CarrierApiException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($truncated) {
            $this->error(sprintf(
                'Janela grande demais (>%d páginas). Watermark NÃO avançou — a próxima execução retoma de onde parou.',
                self::MAX_PAGES,
            ));

            return self::FAILURE;
        }

        if (! $dryRun) {
            Setting::set(self::WATERMARK_KEY, $runStartedAt->toIso8601String());
        }

        $this->info(sprintf(
            '%s%d shipment(s) verificados, %d contrato(s) atualizado(s).',
            $dryRun ? '[dry-run] ' : '',
            $checked,
            count($updated),
        ));

        return self::SUCCESS;
    }

    private function pollTimeToCargo(
        ContractArrivalSyncService $sync,
        TimeToCargoArrivalExtractor $extractor,
    ): int {
        $client = TimeToCargoClient::fromConfig();

        if (! $client->isConfigured()) {
            $this->warn('TimeToCargo não configurado (TRACKING_AGGREGATOR_URL / _KEY). Nada a fazer.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $updated = [];
        $page = 1;
        $truncated = false;

        $this->info('Polling TimeToCargo shipments (ACTIVE)…');

        try {
            do {
                $result = $client->listShipments(page: $page, limit: 100, status: 'ACTIVE');

                foreach ($result['rows'] as $row) {
                    $checked++;

                    $trackNumber = trim((string) data_get($row, 'tracked_item.track_number', ''));
                    $data = $this->nestedTrackingData($row);

                    if ($data === null) {
                        if ($trackNumber === '') {
                            continue;
                        }

                        $type = preg_match('/^[A-Z]{4}[0-9]{7}$/', strtoupper($trackNumber)) === 1
                            ? 'container'
                            : null;
                        $body = $client->track($trackNumber, type: $type);

                        if (($body['status'] ?? '') !== 'OK' || ! is_array($body['data'] ?? null)) {
                            continue;
                        }

                        $data = (array) $body['data'];
                    }

                    $extracted = $extractor->extract($data);
                    $references = array_values(array_unique(array_filter([
                        $trackNumber,
                        ...$extracted['references'],
                        ...array_keys($extracted['arrivals']),
                    ])));

                    if ($references === []) {
                        continue;
                    }

                    foreach ($sync->syncFromNormalized($references, $extracted['arrivals'], persist: ! $dryRun) as $change) {
                        $updated[] = $change;
                        $this->line(sprintf(
                            '  contrato #%d (%s) eta_date → %s',
                            $change['contract_id'],
                            $change['container'],
                            $change['eta_date'],
                        ));
                    }
                }

                $page++;

                if ($result['has_more'] && $page >= self::MAX_PAGES) {
                    $truncated = true;
                    break;
                }
            } while ($result['has_more']);
        } catch (CarrierApiException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($truncated) {
            $this->error(sprintf(
                'Listagem grande demais (>%d páginas). Corrija filtros no TimeToCargo ou reduza o volume.',
                self::MAX_PAGES,
            ));

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s%d shipment(s) verificados, %d contrato(s) atualizado(s).',
            $dryRun ? '[dry-run] ' : '',
            $checked,
            count($updated),
        ));

        return self::SUCCESS;
    }

    /**
     * Shipments list nests the tracking body at tracked_item.data.data.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function nestedTrackingData(array $row): ?array
    {
        $candidates = [
            data_get($row, 'tracked_item.data.data'),
            data_get($row, 'tracked_item.data'),
            data_get($row, 'data'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if (isset($candidate['containers']) || isset($candidate['shipment_status'])) {
                return $candidate;
            }
        }

        return null;
    }

    private function watermark(): Carbon
    {
        $raw = Setting::get(self::WATERMARK_KEY);

        if (is_string($raw) && $raw !== '') {
            $parsed = Carbon::parse($raw);

            if ($parsed->isValid()) {
                return $parsed;
            }
        }

        return now('UTC')->subDays(self::LOOKBACK_ON_FIRST_RUN_DAYS);
    }
}
