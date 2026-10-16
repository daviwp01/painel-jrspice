<?php

namespace App\Services\Contracts\Spreadsheet;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\User;
use App\Services\Contracts\ContractProductCatalog;
use Illuminate\Support\Facades\DB;

final class ContractSpreadsheetImporter
{
    private const EXPIRED_SERIAL = 20000;

    /**
     * Sellers whose platform account carries a different display name.
     * Key is the normalized spreadsheet name, value the account e-mail.
     */
    private const SELLER_ALIASES = [
        'josimar' => 'josimar@xpice.pt',
    ];

    /** @var array<string, int> */
    private array $clientCache = [];

    /** @var array<string, int|null> */
    private array $sellerCache = [];

    /** @var array<string, mixed> */
    private array $report;

    public function __construct(
        private readonly ContractSpreadsheetReader $reader,
        private readonly ValueNormalizer $normalizer,
        private readonly ContractProductCatalog $catalog,
    ) {
        $this->report = self::blankReport();
    }

    /**
     * @return array<string, mixed>
     */
    public function import(string $path, bool $dryRun = false): array
    {
        $this->reset();
        $this->warmCaches();

        $result = $this->reader->read($path);

        $this->report['discarded_rows'] = $result['discarded'];
        $this->report['residue_rows'] = $result['residue_rows'];
        $this->report['warnings'] = $result['warnings'];

        $run = function () use ($result): void {
            foreach ($result['blocks'] as $block) {
                $this->importBlock($block);
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $run();
                DB::rollBack();
            } catch (\Throwable $exception) {
                DB::rollBack();
                throw $exception;
            }
        } else {
            DB::transaction($run);
        }

        $this->report['flagged'] = $this->normalizer->flagged();
        $this->report['merged'] = $this->normalizer->merged();
        $this->report['possible_variants'] = $this->normalizer->possibleVariants();

        return $this->report;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function importBlock(array $block): void
    {
        $header = $block['header'];
        $sourceRow = $block['source_row'];

        $date = $this->normalizer->date($header[ContractSpreadsheetReader::COL_DATE]);
        $contractNumber = $this->normalizer->text($header[ContractSpreadsheetReader::COL_CONTRACT]);

        $exporter = $this->resolveClient(
            $this->normalizer->party($header[ContractSpreadsheetReader::COL_EXPORTER], 'Exportador', $sourceRow),
            'exporter'
        );
        $importer = $this->resolveClient(
            $this->normalizer->party($header[ContractSpreadsheetReader::COL_IMPORTER], 'Importador', $sourceRow),
            'importer'
        );

        $country = $this->normalizer->country($header[ContractSpreadsheetReader::COL_COUNTRY], $sourceRow);
        $seller = $this->resolveSeller($header[ContractSpreadsheetReader::COL_SELLER], $sourceRow);

        $items = $this->buildItems($block['items'], $country);

        $exchangeRate = $this->normalizer->number($header[ContractSpreadsheetReader::COL_EXCHANGE]);
        $video = $this->normalizer->video(
            $header[ContractSpreadsheetReader::COL_VIDEO_REFERENCE],
            $header[ContractSpreadsheetReader::COL_VIDEO_DATE],
            $sourceRow
        );

        $incident = $this->normalizer->incident($header[ContractSpreadsheetReader::COL_INCIDENT]);

        $attributes = [
            'date' => $date,
            'contract_number' => $contractNumber,
            'exporter_id' => $exporter,
            'importer_id' => $importer,
            'country' => $country,
            'seller_id' => $seller,
            'status' => $this->normalizer->status($header[ContractSpreadsheetReader::COL_STATUS], $sourceRow),
            'status_date' => $this->normalizer->date($header[ContractSpreadsheetReader::COL_STATUS_DATE]),
            'dhl_date' => $this->normalizer->dhlDate($header[ContractSpreadsheetReader::COL_DHL_DATE]),
            'dhl_number' => $this->firstText($items, 'dhl_number') ?? $this->normalizer->text($header[ContractSpreadsheetReader::COL_DHL_NUMBER]),
            'etd_date' => $this->firstDate($items, 'etd_date') ?? $this->normalizer->date($header[ContractSpreadsheetReader::COL_ETD]),
            'eta_date' => $this->firstDate($items, 'eta_date') ?? $this->normalizer->date($header[ContractSpreadsheetReader::COL_ETA]),
            'video_sent' => $video['sent'],
            'video_reference' => $video['reference'] ?? $this->firstText($items, 'video_reference'),
            'video_date' => $video['date'] ?? $this->firstDate($items, 'video_date'),
            'incident' => $incident,
            'has_incident' => $incident !== null,
            'estimated_receipt_date' => $this->normalizer->date($header[ContractSpreadsheetReader::COL_ESTIMATED_RECEIPT]),
            'exchange_rate' => $exchangeRate ?? $this->firstNumber($items, 'exchange_rate'),
            'receipt_date' => $this->normalizer->date($header[ContractSpreadsheetReader::COL_RECEIPT]),
            'paid_in_date' => $this->normalizer->date($header[ContractSpreadsheetReader::COL_PAID_IN]),
            'paid_in_brl' => $this->normalizer->number($header[ContractSpreadsheetReader::COL_PAID_BRL]),
            'observations' => $this->normalizer->text($header[ContractSpreadsheetReader::COL_OBSERVATIONS]),
            'source_row' => $sourceRow,
            'updated_at' => now(),
        ];

        $contract = Contract::query()->where('source_row', $sourceRow)->first();

        if ($contract === null) {
            $contract = Contract::create($attributes + ['created_at' => now()]);
            $this->report['contracts_created']++;
        } else {
            $contract->update($attributes);
            $this->report['contracts_updated']++;
        }

        $this->persistItems($contract, $items, $attributes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $rows, ?string $country): array
    {
        $items = [];

        foreach ($rows as $index => $row) {
            $values = $row['values'];
            $formulas = $row['formulas'];

            $quantity = $this->normalizer->number($values[ContractSpreadsheetReader::COL_QUANTITY]);
            $price = $this->normalizer->number($values[ContractSpreadsheetReader::COL_PRICE]);
            $sheetSales = $this->normalizer->number($values[ContractSpreadsheetReader::COL_SALES]);

            $sales = ($quantity !== null || $price !== null)
                ? round((float) ($quantity ?? 0) * (float) ($price ?? 0), 2)
                : $sheetSales;

            $commission = $this->normalizer->number($values[ContractSpreadsheetReader::COL_COMMISSION]);
            $toPay = $this->normalizer->number($values[ContractSpreadsheetReader::COL_TO_PAY]);
            $exchangeRate = $this->normalizer->number($values[ContractSpreadsheetReader::COL_EXCHANGE]);
            $sheetEuro = $this->normalizer->number($values[ContractSpreadsheetReader::COL_EURO]);

            $commissionRate = $this->normalizer->commissionRate(
                $formulas[ContractSpreadsheetReader::COL_COMMISSION] ?? null,
                $commission,
                $sales
            );
            $toPayRate = $this->normalizer->toPayRate(
                $formulas[ContractSpreadsheetReader::COL_TO_PAY] ?? null,
                $toPay,
                $sales
            );

            $video = $this->normalizer->video(
                $values[ContractSpreadsheetReader::COL_VIDEO_REFERENCE],
                $values[ContractSpreadsheetReader::COL_VIDEO_DATE],
                $row['source_row']
            );

            $items[] = [
                'source_row' => $row['source_row'],
                'register_number' => $this->normalizer->text($values[ContractSpreadsheetReader::COL_REGISTER]),
                'product_name' => $this->normalizer->text($values[ContractSpreadsheetReader::COL_PRODUCT]),
                'quantity_tons' => $quantity,
                'price_per_ton_usd' => $price,
                'sales_usd' => $sales,
                'commission_rate' => $commissionRate,
                'commission_usd' => $commission,
                'exchange_rate' => $exchangeRate,
                'estimated_euro' => $this->estimatedEuro($commission, $exchangeRate, $sheetEuro),
                'to_pay_rate' => $toPayRate,
                'to_pay_usd' => $toPay,
                'etd_date' => $this->normalizer->date($values[ContractSpreadsheetReader::COL_ETD]),
                'eta_date' => $this->normalizer->date($values[ContractSpreadsheetReader::COL_ETA]),
                'dhl_number' => $this->normalizer->trackingCode(
                    $values[ContractSpreadsheetReader::COL_DHL_NUMBER],
                    'DHL Nº',
                    $row['source_row']
                ),
                'status_date' => $this->normalizer->date($values[ContractSpreadsheetReader::COL_STATUS_DATE]),
                'video_date' => $video['date'],
                'video_reference' => $video['reference'],
                'observations' => $this->normalizer->text($values[ContractSpreadsheetReader::COL_OBSERVATIONS]),
                '_country' => $country,
            ];
        }

        return $items;
    }

    private function estimatedEuro(?float $commission, ?float $exchangeRate, ?float $sheetEuro): ?float
    {
        if ($commission !== null && $exchangeRate !== null) {
            return round($commission * $exchangeRate, 3);
        }

        return $sheetEuro;
    }

    /**
     * Contract level values are taken from the first non-empty line; line values
     * that differ from the contract default are stored as item overrides.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $attributes
     */
    private function persistItems(Contract $contract, array $items, array $attributes): void
    {
        $keepIds = [];

        foreach (array_values($items) as $position => $item) {
            $product = $this->catalog->resolve($item['product_name']);
            if ($product !== null && $product->wasRecentlyCreated) {
                $this->report['products_created']++;
            }

            $this->ensureClientCountry($attributes, $item['_country']);

            $row = [
                'position' => $position + 1,
                'register_number' => $item['register_number'],
                'product_id' => $product?->id,
                'quantity_tons' => $item['quantity_tons'],
                'price_per_ton_usd' => $item['price_per_ton_usd'],
                'sales_usd' => $item['sales_usd'],
                'commission_rate' => $item['commission_rate'],
                'commission_usd' => $item['commission_usd'],
                'exchange_rate' => $item['exchange_rate'],
                'estimated_euro' => $item['estimated_euro'],
                'to_pay_rate' => $item['to_pay_rate'],
                'to_pay_usd' => $item['to_pay_usd'],
                'etd_date' => $this->override($item['etd_date'], $attributes['etd_date']),
                'eta_date' => $this->override($item['eta_date'], $attributes['eta_date']),
                'dhl_number' => $this->override($item['dhl_number'], $attributes['dhl_number']),
                'status_date' => $this->override($item['status_date'], $attributes['status_date']),
                'video_date' => $this->override($item['video_date'], $attributes['video_date']),
                'video_reference' => $this->override($item['video_reference'], $attributes['video_reference']),
                'observations' => $this->override($item['observations'], $attributes['observations']),
                'source_row' => $item['source_row'],
                'updated_at' => now(),
            ];

            $existing = $contract->items()->where('source_row', $item['source_row'])->first();

            if ($existing === null) {
                $keepIds[] = ContractItem::create($row + [
                    'contract_id' => $contract->id,
                    'created_at' => now(),
                ])->getKey();

                $this->report['items_created']++;
            } else {
                $existing->update($row);
                $keepIds[] = $existing->getKey();

                $this->report['items_updated']++;
            }
        }

        $removed = $contract->items()
            ->whereNotNull('source_row')
            ->whereNotIn('id', $keepIds)
            ->delete();

        $this->report['items_removed'] += (int) $removed;
    }

    private function override(?string $value, ?string $contractDefault): ?string
    {
        return $value !== null && $value !== $contractDefault ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ensureClientCountry(array $attributes, ?string $country): void
    {
        if ($country === null) {
            return;
        }

        foreach (['exporter_id', 'importer_id'] as $key) {
            if (empty($attributes[$key])) {
                continue;
            }

            Client::query()
                ->whereKey($attributes[$key])
                ->whereNull('country')
                ->update(['country' => $country]);
        }
    }

    private function resolveClient(?string $name, string $type): ?int
    {
        if ($name === null) {
            return null;
        }

        $cacheKey = $type.'|'.$this->catalog->normalize($name);

        if (array_key_exists($cacheKey, $this->clientCache)) {
            return $this->clientCache[$cacheKey];
        }

        $client = Client::query()
            ->where('type', $type)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($client === null) {
            $client = Client::create([
                'name' => $name,
                'type' => $type,
                'country' => null,
            ]);

            $this->report['clients_created']++;
        }

        return $this->clientCache[$cacheKey] = $client->id;
    }

    private function resolveSeller(mixed $value, int $row): ?int
    {
        $name = $this->normalizer->seller($value, $row);
        if ($name === null) {
            return null;
        }

        $key = $this->catalog->normalize($name);

        if (array_key_exists($key, $this->sellerCache)) {
            return $this->sellerCache[$key];
        }

        $user = User::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->orderBy('id')
            ->first();

        if ($user === null) {
            $alias = self::SELLER_ALIASES[$this->catalog->normalize($name)] ?? null;

            if ($alias !== null) {
                $user = User::query()->where('email', $alias)->first();
            }
        }

        if ($user === null) {
            $this->report['unmatched_sellers'][] = $name;
        }

        return $this->sellerCache[$key] = $user?->id;
    }

    private function warmCaches(): void
    {
        foreach (Client::query()->select('id', 'name', 'type')->get() as $client) {
            $this->clientCache[$client->type.'|'.$this->catalog->normalize($client->name)] = $client->id;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function firstText(array $items, string $key): ?string
    {
        foreach ($items as $item) {
            if ($item[$key] !== null) {
                return $item[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function firstDate(array $items, string $key): ?string
    {
        return $this->firstText($items, $key);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function firstNumber(array $items, string $key): ?float
    {
        foreach ($items as $item) {
            if ($item[$key] !== null) {
                return $item[$key];
            }
        }

        return null;
    }

    private function reset(): void
    {
        $this->clientCache = [];
        $this->sellerCache = [];
        $this->report = self::blankReport();
    }

    /**
     * @return array<string, mixed>
     */
    private static function blankReport(): array
    {
        return [
            'contracts_created' => 0,
            'contracts_updated' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_removed' => 0,
            'products_created' => 0,
            'clients_created' => 0,
            'discarded_rows' => [],
            'residue_rows' => [],
            'warnings' => [],
            'flagged' => [],
            'merged' => [],
            'possible_variants' => [],
            'unmatched_sellers' => [],
        ];
    }
}
