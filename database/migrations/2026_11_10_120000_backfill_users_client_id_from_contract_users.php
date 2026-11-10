<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill users.client_id from the legacy contract_users pivot so portal
     * access keeps working after the switch to company-based linking.
     */
    public function up(): void
    {
        if (! Schema::hasTable('contract_users') || ! Schema::hasColumn('users', 'client_id')) {
            return;
        }

        $links = DB::table('contract_users')
            ->join('contracts', 'contracts.id', '=', 'contract_users.contract_id')
            ->join('users', 'users.id', '=', 'contract_users.user_id')
            ->whereNull('users.client_id')
            ->select('contract_users.user_id', 'contracts.exporter_id', 'contracts.importer_id')
            ->get();

        foreach ($links as $link) {
            $clientId = $link->exporter_id ?? $link->importer_id;

            if ($clientId === null) {
                continue;
            }

            DB::table('users')
                ->where('id', $link->user_id)
                ->whereNull('client_id')
                ->update(['client_id' => $clientId]);
        }
    }

    public function down(): void
    {
        // Intentionally left blank: backfilled client_id values are not reversible.
    }
};
