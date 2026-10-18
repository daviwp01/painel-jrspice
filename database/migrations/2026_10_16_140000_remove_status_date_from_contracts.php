<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('contracts', 'status_date')) {
            // "Data Status" was promoted to "Data embarque ETD": it wins over the
            // original ETD value, and rows without it keep their ETD untouched.
            DB::table('contracts')
                ->whereNotNull('status_date')
                ->update(['etd_date' => DB::raw('status_date')]);

            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('status_date');
            });
        }

        if (Schema::hasColumn('contract_items', 'status_date')) {
            DB::table('contract_items')
                ->whereNotNull('status_date')
                ->update(['etd_date' => DB::raw('status_date')]);

            Schema::table('contract_items', function (Blueprint $table) {
                $table->dropColumn('status_date');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('contracts', 'status_date')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->date('status_date')->nullable()->after('status');
            });
        }

        if (! Schema::hasColumn('contract_items', 'status_date')) {
            Schema::table('contract_items', function (Blueprint $table) {
                $table->date('status_date')->nullable()->after('dhl_number');
            });
        }
    }
};
