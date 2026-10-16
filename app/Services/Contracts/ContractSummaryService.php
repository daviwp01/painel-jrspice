<?php

namespace App\Services\Contracts;

use Illuminate\Support\Facades\DB;

final class ContractSummaryService
{
    /**
     * @return array{total_tons: float, pending_commissions: float, delayed_logistics: int, active_contracts: int, total_contracts: int, total_sales_usd: float}
     */
    public function overview(): array
    {
        $totals = DB::table('contract_items')
            ->selectRaw('COALESCE(SUM(quantity_tons), 0) AS total_tons')
            ->selectRaw('COALESCE(SUM(sales_usd), 0) AS total_sales_usd')
            ->first();

        $pendingCommissions = (float) DB::table('contract_items as it')
            ->join('contracts as c', 'c.id', '=', 'it.contract_id')
            ->whereNull('c.paid_in_date')
            ->selectRaw('COALESCE(SUM(it.commission_usd), 0) AS total')
            ->first()
            ->total;

        $delayedLogistics = DB::table('contracts')
            ->tap(fn ($query) => ContractQueryService::applyDelayedScope($query))
            ->count();

        $activeContracts = DB::table('contracts')
            ->where(function ($query) {
                ContractQueryService::applyActiveStatusConstraint($query);
            })
            ->count();

        return [
            'total_tons' => (float) $totals->total_tons,
            'total_sales_usd' => (float) $totals->total_sales_usd,
            'pending_commissions' => (float) $pendingCommissions,
            'delayed_logistics' => (int) $delayedLogistics,
            'active_contracts' => (int) $activeContracts,
            'total_contracts' => (int) DB::table('contracts')->count(),
        ];
    }
}
