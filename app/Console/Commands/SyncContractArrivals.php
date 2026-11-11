<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Tracking\CarrierApiException;
use App\Models\Setting;
use App\Services\ContainerTracking\Tracktainer\ContractArrivalSyncService;
use App\Services\ContainerTracking\Tracktainer\TracktainerClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Polls Tracktainer for shipment updates and writes arrival dates onto
 * matching contracts (contracts.eta_date).
 *
 * Tracktainer has no webhooks yet (docs: "Coming soon… polling with
 * filter[updated_after]"), so this scheduled command is the official path.
 * The watermark lives in the settings table and advances every run; a failed
 * run leaves it untouched so the next attempt retries the same window.
 */
class SyncContractArrivals extends Command
{
    private const WATERMARK_KEY = 'tracktainer_sync_after';

    private const LOOKBACK_ON_FIRST_RUN_DAYS = 30;

    /**
     * Safety valve: the API pages at ~25 rows; a runaway window must not spin
     * forever. When the cap is hit the watermark is left untouched so the next
     * run continues the same window instead of dropping the tail.
     */
    private const MAX_PAGES = 50;

    protected $signature = 'tracking:sync-arrivals {--dry-run : List what would change without writing}';

    protected $description = 'Sincroniza contracts.eta_date com a chegada real reportada pelo Tracktainer';

    public function handle(ContractArrivalSyncService $sync): int
    {
        $client = TracktainerClient::fromConfig();

        if (! $client->isConfigured()) {
            $this->warn('Tracktainer não configurado (TRACKING_AGGREGATOR_URL / _KEY). Nada a fazer.');

            return self::SUCCESS;
        }

        $watermark = $this->watermark();
        // Captured before the first list call so shipments updated while this
        // run is paging stay inside the next window instead of being skipped.
        $runStartedAt = now('UTC');
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $updated = [];
        $page = 1;
        $truncated = false;

        $this->info(sprintf('Polling shipments updated since %s…', $watermark->toIso8601String()));

        try {
            do {
                $result = $client->listShipments([
                    'filter[updated_after]' => $watermark->toIso8601String(),
                ], $page);

                foreach ($result['data'] as $row) {
                    $checked++;
                    $attributes = (array) ($row['attributes'] ?? []);

                    $changes = $sync->syncShipment($attributes, persist: ! $dryRun);

                    foreach ($changes as $change) {
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

            // Watermark untouched: the next run retries this window.
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
            // Advance only after a fully successful, untruncated pass — using
            // the timestamp from before the first fetch.
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
