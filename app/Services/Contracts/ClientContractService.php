<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\User;
use App\Support\ContractStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ClientContractService
{
    /**
     * Query-string keys understood by the portal filter bar.
     */
    public const FILTER_KEYS = [
        'search',
        'contract_number',
        'product_id',
        'country',
        'status',
        'date_from',
        'date_to',
        'active_only',
        'has_incident',
    ];

    public function paginateForUser(User $user, Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseQuery($user)
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderByDesc('date')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Contract $contract) => $this->present($contract));
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

    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when(
                $this->statusList($request) !== [],
                fn (Builder $q) => $q->whereIn('status', $this->statusList($request))
            )
            ->when($request->filled('contract_number'), fn (Builder $q) => $q->where('contract_number', 'like', '%'.trim($request->string('contract_number')).'%'))
            ->when($request->filled('country'), fn (Builder $q) => $q->where('country', 'like', '%'.trim($request->string('country')).'%'))
            ->when($request->filled('product_id'), function (Builder $q) use ($request) {
                $q->whereHas('items', fn (Builder $item) => $item->where('product_id', $request->integer('product_id')));
            })
            ->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('date', '<=', $request->date('date_to')))
            ->when($request->boolean('active_only'), fn (Builder $q) => $q->where(function (Builder $inner) {
                $inner->where('status', '!=', ContractStatus::FINISHED)->orWhereNull('status');
            }))
            ->when($request->boolean('has_incident'), fn (Builder $q) => $q->where('incident', '!=', ''))
            ->when($request->filled('search'), fn (Builder $q) => $this->applySearch($q, $request->string('search')->toString()));
    }

    /**
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

    /**
     * One aggregate pass instead of three separate queries: the pivot is joined
     * once and the item join feeds both the tonnage and the contract counts.
     *
     * @return array{total_tons: float, total_contracts: int, active_shipments: int}
     */
    public function summaryForUser(User $user): array
    {
        $row = DB::table('contracts')
            ->join('contract_users', 'contract_users.contract_id', '=', 'contracts.id')
            ->where('contract_users.user_id', $user->id)
            ->leftJoin('contract_items', 'contract_items.contract_id', '=', 'contracts.id')
            ->selectRaw('
                COUNT(DISTINCT contracts.id) AS total_contracts,
                COUNT(DISTINCT CASE WHEN contracts.status IS NULL OR contracts.status <> ? THEN contracts.id END) AS active_shipments,
                COALESCE(SUM(contract_items.quantity_tons), 0) AS total_tons
            ', [ContractStatus::FINISHED])
            ->first();

        return [
            'total_tons' => (float) ($row->total_tons ?? 0),
            'total_contracts' => (int) ($row->total_contracts ?? 0),
            'active_shipments' => (int) ($row->active_shipments ?? 0),
        ];
    }

    public function present(Contract $contract, bool $withItems = false): array
    {
        $items = $contract->relationLoaded('items') ? $contract->items : collect();
        $firstItem = $items->first();

        return [
            'id' => $contract->id,
            'date' => $contract->date?->toDateString(),
            'contract_number' => $contract->contract_number,
            'register_number' => $firstItem?->register_number,
            'country' => $contract->country,
            'status' => $contract->status,
            'status_date' => $contract->status_date?->toDateString(),
            'etd_date' => $contract->etd_date?->toDateString(),
            'eta_date' => $contract->eta_date?->toDateString(),
            'dhl_date' => $contract->dhl_date?->toDateString(),
            'dhl_number' => $contract->dhl_number,
            'container_number' => $contract->container_number,
            'shipping_company' => $contract->shipping_company,
            'incident' => $contract->incident,
            'observations' => $contract->observations,
            'video_sent' => $contract->video_sent,
            'video_date' => $contract->video_date?->toDateString(),
            'exporter_id' => $contract->exporter_id,
            'importer_id' => $contract->importer_id,
            'exporter' => $contract->exporter,
            'importer' => $contract->importer,
            'seller' => $contract->seller,
            'items_count' => $contract->items_count ?? $items->count(),
            'quantity_tons' => $contract->total_quantity_tons ?? $items->sum('quantity_tons'),
            'sales_usd' => $contract->total_sales_usd ?? $items->sum('sales_usd'),
            'price_per_ton_usd' => $firstItem?->price_per_ton_usd,
            'commission_usd' => $items->sum('commission_usd'),
            'estimated_euro' => $items->sum('estimated_euro'),
            'product' => $firstItem?->product,
            'documents' => $withItems && $contract->relationLoaded('documents') ? $contract->documents : null,
            'items' => $withItems ? $items->values() : null,
        ];
    }

    private function baseQuery(User $user): Builder
    {
        return Contract::query()
            ->whereHas('users', fn (Builder $q) => $q->where('users.id', $user->id))
            ->with(['exporter:id,name', 'importer:id,name', 'seller:id,name'])
            ->withCount('items')
            ->withSum('items as total_quantity_tons', 'quantity_tons')
            ->withSum('items as total_sales_usd', 'sales_usd')
            ->with(['items' => function ($query) {
                $query->select([
                    'id', 'contract_id', 'position', 'register_number', 'product_id',
                    'quantity_tons', 'price_per_ton_usd', 'commission_usd', 'estimated_euro',
                ])->orderBy('position');
            }])
            ->with('items.product:id,name');
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('contract_number', 'like', $like)
                ->orWhere('country', 'like', $like)
                ->orWhereHas('items', fn (Builder $iq) => $iq->where('register_number', 'like', $like))
                ->orWhereHas('items', fn (Builder $iq) => $iq->whereHas('product', fn (Builder $p) => $p->where('name', 'like', $like)));
        });
    }
}
