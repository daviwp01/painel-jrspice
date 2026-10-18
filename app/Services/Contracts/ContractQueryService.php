<?php

namespace App\Services\Contracts;

use App\Support\ContractStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ContractQueryService
{
    /**
     * Column expressions of the flattened listing (one row per contract item).
     * The aliases mirror the exact spreadsheet headings.
     */
    private const COLUMNS = [
        'it.id AS item_id',
        'c.id AS contract_id',
        'c.date AS date',
        'c.contract_number AS contract_number',
        'it.register_number AS register_number',
        'c.exporter_id AS exporter_id',
        "COALESCE(ce.name, '') AS exporter_name",
        'c.importer_id AS importer_id',
        "COALESCE(cl_i.name, '') AS importer_name",
        "COALESCE(c.country, '') AS country",
        'it.product_id AS product_id',
        "COALESCE(cp.name, '') AS product_name",
        'it.quantity_tons AS quantity_tons',
        'it.price_per_ton_usd AS price_per_ton_usd',
        'it.sales_usd AS sales_usd',
        'it.commission_rate AS commission_rate',
        'it.commission_usd AS commission_usd',
        'COALESCE(it.exchange_rate, c.exchange_rate) AS exchange_rate',
        'it.estimated_euro AS estimated_euro',
        'c.estimated_receipt_date AS estimated_receipt_date',
        'c.seller_id AS seller_id',
        "COALESCE(u.name, '') AS seller_name",
        'it.to_pay_rate AS to_pay_rate',
        'it.to_pay_usd AS to_pay_usd',
        'c.receipt_date AS receipt_date',
        'c.paid_in_date AS paid_in_date',
        'c.paid_in_brl AS paid_in_brl',
        "COALESCE(c.incident, '') AS incident",
        'c.video_sent AS video_sent',
        'COALESCE(it.video_reference, c.video_reference) AS video_reference',
        'COALESCE(it.video_date, c.video_date) AS video_date',
        'c.status AS status',
        'c.dhl_date AS dhl_date',
        'COALESCE(it.etd_date, c.etd_date) AS etd_date',
        'COALESCE(it.eta_date, c.eta_date) AS eta_date',
        "COALESCE(NULLIF(it.dhl_number, ''), c.dhl_number) AS dhl_number",
        "COALESCE(NULLIF(it.observations, ''), c.observations) AS observations",
        'c.shipping_company AS shipping_company',
        'c.container_number AS container_number',
    ];

    /**
     * Query-string keys understood by the filter panel.
     */
    public const FILTER_KEYS = [
        'search',
        'contract_number',
        'register_number',
        'exporter_id',
        'importer_id',
        'country',
        'seller_id',
        'product_id',
        'status',
        'date_from',
        'date_to',
        'active_only',
        'delayed',
        'has_incident',
        'pending_commission',
    ];

    /**
     * An ETA inside this window without a DHL tracking number is considered
     * delayed logistics. Shared with ContractSummaryService so the stats card
     * and the filter can never diverge.
     */
    public const DELAY_WINDOW_DAYS = 7;

    /**
     * Columns the listing may be ordered by. The whitelist keeps the public
     * query string from reaching raw SQL identifiers.
     */
    public const SORTABLE = ['date'];

    public const DEFAULT_SORT = 'date';

    public const DEFAULT_DIRECTION = 'desc';

    /**
     * Accepted `per_page` values. Anything else falls back to the default so a
     * crafted query string can never ask the database for an unbounded page.
     */
    public const PER_PAGE_OPTIONS = ['25', '50', '100', '200', '500'];

    /** Sentinel for "show everything": a hard ceiling, never an unbounded scan. */
    public const PER_PAGE_ALL = 'all';

    public const PER_PAGE_CEILING = 5000;

    public const DEFAULT_PER_PAGE = 50;

    /**
     * A contract is delayed when the estimated arrival is close (or past) and
     * the shipment still has no tracking number, and the process is not finished.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyDelayedScope($query, string $eta = 'eta_date', string $dhl = 'dhl_number', string $status = 'status'): void
    {
        $query->whereNotNull($eta)
            ->where($eta, '<', now()->addDays(self::DELAY_WINDOW_DAYS))
            ->whereNull($dhl)
            ->where(function ($inner) use ($status) {
                self::applyActiveStatusConstraint($inner, $status);
            });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyActiveStatusConstraint($query, string $status = 'status'): void
    {
        $query->where($status, '!=', ContractStatus::FINISHED)
            ->orWhereNull($status);
    }

    /**
     * Resolves the requested page size, normalised to the options the UI offers.
     */
    public function perPage(Request $request): int
    {
        $raw = trim((string) $request->input('per_page', ''));

        if ($raw === self::PER_PAGE_ALL) {
            return self::PER_PAGE_CEILING;
        }

        return in_array($raw, self::PER_PAGE_OPTIONS, true)
            ? (int) $raw
            : self::DEFAULT_PER_PAGE;
    }

    /**
     * The label the UI must keep selected, so `per_page=all` round-trips.
     */
    public function perPageOption(Request $request): string
    {
        $raw = trim((string) $request->input('per_page', ''));

        if ($raw === self::PER_PAGE_ALL) {
            return self::PER_PAGE_ALL;
        }

        return in_array($raw, self::PER_PAGE_OPTIONS, true)
            ? $raw
            : (string) self::DEFAULT_PER_PAGE;
    }

    public function paginate(Request $request, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        return DB::query()
            ->fromSub($this->baseQuery(), 'contract_rows')
            ->tap(fn ($query) => $this->applyFilters($query, $request))
            ->tap(fn ($query) => $this->applyOrder($query, $request))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (object $row) => $this->transform($row));
    }

    public function exportable(Request $request)
    {
        return DB::query()
            ->fromSub($this->baseQuery(), 'contract_rows')
            ->tap(fn ($query) => $this->applyFilters($query, $request))
            ->tap(fn ($query) => $this->applyOrder($query, $request))
            ->get();
    }

    public function sortField(Request $request): string
    {
        $field = $request->string('sort_by')->toString();

        return in_array($field, self::SORTABLE, true) ? $field : self::DEFAULT_SORT;
    }

    public function sortDirection(Request $request): string
    {
        return strtolower($request->string('sort_dir')->toString()) === 'asc'
            ? 'asc'
            : self::DEFAULT_DIRECTION;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyOrder($query, Request $request): void
    {
        $direction = $this->sortDirection($request);

        $query
            ->orderBy($this->sortField($request), $direction)
            ->orderBy('contract_id', $direction)
            ->orderBy('item_position', 'asc');
    }

    /**
     * @return array<string, mixed>
     */
    public function activeFilters(Request $request): array
    {
        $active = [];

        foreach (self::FILTER_KEYS as $key) {
            $value = $request->input($key);

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (is_array($value)) {
                $value = array_values(array_filter($value, static fn ($item) => $item !== '' && $item !== null));
                if ($value === []) {
                    continue;
                }
            }

            $active[$key] = $value;
        }

        return $active;
    }

    public function statusOptions(): array
    {
        return ContractStatus::options();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyFilters($query, Request $request): void
    {
        $query
            ->when(
                $this->statusList($request) !== [],
                fn ($q) => $q->whereIn('status', $this->statusList($request))
            )
            ->when($request->filled('contract_number'), fn ($q) => $q->where('contract_number', 'like', '%'.trim($request->string('contract_number')).'%'))
            ->when($request->filled('register_number'), fn ($q) => $q->where('register_number', 'like', '%'.trim($request->string('register_number')).'%'))
            ->when($request->filled('country'), fn ($q) => $q->where('country', 'like', '%'.trim($request->string('country')).'%'))
            ->when($request->filled('exporter_id'), fn ($q) => $q->where('exporter_id', $request->integer('exporter_id')))
            ->when($request->filled('importer_id'), fn ($q) => $q->where('importer_id', $request->integer('importer_id')))
            ->when($request->filled('seller_id'), fn ($q) => $q->where('seller_id', $request->integer('seller_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('date', '<=', $request->date('date_to')))
            ->when($request->boolean('active_only'), function ($q) {
                $q->where(function ($inner) {
                    self::applyActiveStatusConstraint($inner);
                });
            })
            ->when($request->boolean('delayed'), function ($q) {
                self::applyDelayedScope($q);
            })
            ->when($request->boolean('has_incident'), fn ($q) => $q->where('incident', '!=', ''))
            ->when($request->boolean('pending_commission'), fn ($q) => $q->whereNull('paid_in_date'))
            ->when($request->filled('search'), fn ($q) => $this->applySearch($q, $request->string('search')->toString()));
    }

    /**
     * Accepts both `status=X` and `status[]=X&status[]=Y`.
     *
     * @return array<int, string>
     */
    private function statusList(Request $request): array
    {
        $status = $request->input('status');

        if (is_string($status)) {
            $status = $status === '' ? [] : [$status];
        }

        if (! is_array($status)) {
            return [];
        }

        return array_values(array_filter($status, static fn ($value) => is_string($value) && $value !== ''));
    }

    private function baseQuery()
    {
        $columns = implode(",\n            ", self::COLUMNS);

        return DB::table('contract_items as it')
            ->join('contracts as c', 'c.id', '=', 'it.contract_id')
            ->leftJoin('clients as ce', 'ce.id', '=', 'c.exporter_id')
            ->leftJoin('clients as cl_i', 'cl_i.id', '=', 'c.importer_id')
            ->leftJoin('contract_products as cp', 'cp.id', '=', 'it.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.seller_id')
            ->selectRaw("{$columns},
                it.position AS item_position,
                SUM(it.sales_usd) OVER (ORDER BY c.date, c.id, it.position) AS cumulative_sales_usd,
                SUM(it.commission_usd) OVER (ORDER BY c.date, c.id, it.position) AS cumulative_commission_usd
            ");
    }

    private function applySearch($query, string $search)
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('contract_number', 'like', $like)
                ->orWhere('register_number', 'like', $like)
                ->orWhere('exporter_name', 'like', $like)
                ->orWhere('importer_name', 'like', $like)
                ->orWhere('product_name', 'like', $like)
                ->orWhere('country', 'like', $like)
                ->orWhere('dhl_number', 'like', $like);
        });
    }

    private function transform(object $row): array
    {
        return [
            'id' => (int) $row->item_id,
            'contract_id' => (int) $row->contract_id,
            'item_position' => (int) $row->item_position,
            'date' => $this->asDate($row->date),
            'contract_number' => $row->contract_number,
            'register_number' => $row->register_number,
            'exporter' => $row->exporter_id ? ['id' => (int) $row->exporter_id, 'name' => $row->exporter_name] : null,
            'importer' => $row->importer_id ? ['id' => (int) $row->importer_id, 'name' => $row->importer_name] : null,
            'country' => $row->country ?: null,
            'product' => $row->product_id ? ['id' => (int) $row->product_id, 'name' => $row->product_name] : null,
            'quantity_tons' => $row->quantity_tons,
            'price_per_ton_usd' => $row->price_per_ton_usd,
            'sales_usd' => $row->sales_usd,
            'cumulative_sales_usd' => $row->cumulative_sales_usd,
            'commission_rate' => $row->commission_rate,
            'commission_usd' => $row->commission_usd,
            'cumulative_commission_usd' => $row->cumulative_commission_usd,
            'exchange_rate' => $row->exchange_rate,
            'estimated_euro' => $row->estimated_euro,
            'estimated_receipt_date' => $this->asDate($row->estimated_receipt_date),
            'seller' => $row->seller_id ? ['id' => (int) $row->seller_id, 'name' => $row->seller_name] : null,
            'to_pay_rate' => $row->to_pay_rate,
            'to_pay_usd' => $row->to_pay_usd,
            'receipt_date' => $this->asDate($row->receipt_date),
            'paid_in_date' => $this->asDate($row->paid_in_date),
            'paid_in_brl' => $row->paid_in_brl,
            'incident' => $row->incident ?: null,
            'video_sent' => (bool) $row->video_sent,
            'video_reference' => $row->video_reference ?: null,
            'video_date' => $this->asDate($row->video_date),
            'status' => $row->status,
            'dhl_date' => $this->asDate($row->dhl_date),
            'etd_date' => $this->asDate($row->etd_date),
            'eta_date' => $this->asDate($row->eta_date),
            'dhl_number' => $row->dhl_number ?: null,
            'observations' => $row->observations ?: null,
            'shipping_company' => $row->shipping_company,
            'container_number' => $row->container_number,
        ];
    }

    /**
     * Keeps the public contract stable across drivers: SQLite may return a
     * full timestamp for a DATE column where MySQL returns `Y-m-d`.
     */
    private function asDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 10);
    }
}
