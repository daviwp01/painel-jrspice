<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUS_ALIASES = [
        'Transbordo em curso' => 'Transbordo até',
        'Transbordo' => 'Transbordo até',
    ];

    private const SUPPORT_TABLES = [
        'contract_users' => 'export_process_users',
        'contract_documents' => 'export_process_documents',
        'contract_reviews' => 'process_reviews',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('export_processes')) {
            return;
        }

        $this->renameSupportTables();
        $this->copyContractsKeepingIds();
        $this->repointForeignKeysToContracts();

        Schema::dropIfExists('export_processes');
    }

    public function down(): void
    {
        if (Schema::hasTable('export_processes')) {
            return;
        }

        $this->recreateLegacyContractsTable();
        $this->restoreFromContracts();
        $this->restoreSupportTables();
    }

    /**
     * The renamed support tables keep pointing at the legacy ids, so the new
     * contracts table must reuse those exact ids instead of generating new ones.
     */
    private function copyContractsKeepingIds(): void
    {
        $productMap = $this->buildContractProductMap();
        $alreadyCopied = DB::table('contracts')->pluck('id')->flip()->all();

        DB::table('export_processes')->orderBy('id')->chunkById(200, function ($rows) use ($productMap, &$alreadyCopied) {
            foreach ($rows as $row) {
                if (isset($alreadyCopied[$row->id])) {
                    continue;
                }

                $now = now();

                DB::table('contracts')->insert([
                    'id' => $row->id,
                    'date' => $row->date,
                    'contract_number' => $row->contract_number,
                    'exporter_id' => $row->exporter_id,
                    'importer_id' => $row->importer_id,
                    'country' => $this->resolveCountry($row->importer_id, $row->exporter_id),
                    'seller_id' => $row->seller_id,
                    'status' => $this->normalizeStatus($row->status),
                    'status_date' => $row->status_date,
                    'dhl_date' => $row->dhl_date,
                    'dhl_number' => $row->dhl_number,
                    'etd_date' => $row->etd_date,
                    'eta_date' => $row->eta_date,
                    'video_sent' => (bool) $row->video_sent,
                    'video_reference' => null,
                    'video_date' => $row->video_date,
                    'incident' => $row->incident,
                    'estimated_receipt_date' => $row->estimated_receipt_date,
                    'exchange_rate' => $row->exchange_rate,
                    'receipt_date' => $row->receipt_date,
                    'paid_in_date' => $row->paid_in_date,
                    'paid_in_brl' => $row->paid_in_brl,
                    'observations' => $row->observations,
                    'shipping_company' => $row->shipping_company,
                    'container_number' => $row->container_number,
                    'source_row' => null,
                    'has_incident' => filled($row->incident),
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]);

                DB::table('contract_items')->insert([
                    'contract_id' => $row->id,
                    'position' => 1,
                    'register_number' => $row->register_number,
                    'product_id' => $productMap[$row->product_id] ?? null,
                    'quantity_tons' => $row->quantity_tons,
                    'price_per_ton_usd' => $row->price_per_ton_usd,
                    'sales_usd' => $row->sales_usd,
                    'commission_rate' => null,
                    'commission_usd' => $row->commission_usd,
                    'exchange_rate' => $row->exchange_rate,
                    'estimated_euro' => $row->estimated_euro,
                    'to_pay_rate' => null,
                    'to_pay_usd' => $row->to_pay_usd,
                    'source_row' => null,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]);

                $alreadyCopied[$row->id] = true;
            }
        });
    }

    private function renameSupportTables(): void
    {
        foreach (self::SUPPORT_TABLES as $current => $legacy) {
            if (! Schema::hasTable($legacy) || Schema::hasTable($current)) {
                continue;
            }

            // The existing unique index tracks the column by position, so it
            // survives the rename and keeps protecting against duplicates.
            Schema::table($legacy, function (Blueprint $table) {
                $table->renameColumn('export_process_id', 'contract_id');
            });

            Schema::rename($legacy, $current);
        }
    }

    /**
     * Renaming a table keeps the old referenced table name inside the foreign
     * key definitions, so they have to be recreated against `contracts`.
     */
    private function repointForeignKeysToContracts(): void
    {
        foreach (array_keys(self::SUPPORT_TABLES) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->dropForeignKeysOn($table, 'contract_id');

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('contract_id')
                    ->references('id')
                    ->on('contracts')
                    ->cascadeOnDelete();
            });
        }
    }

    private function dropForeignKeysOn(string $table, string $column): void
    {
        if (DB::getDriverName() === 'mysql') {
            $constraints = DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?
                   AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$table, $column]
            );

            foreach ($constraints as $constraint) {
                Schema::table($table, function (Blueprint $blueprint) use ($constraint) {
                    $blueprint->dropForeign($constraint->CONSTRAINT_NAME);
                });
            }

            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // The driver rebuilt the table without a foreign key on that column.
        }
    }

    private function recreateLegacyContractsTable(): void
    {
        Schema::create('export_processes', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->string('contract_number')->nullable();
            $table->string('register_number')->nullable();
            $table->foreignId('exporter_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('importer_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('quantity_tons', 10, 2)->nullable();
            $table->decimal('price_per_ton_usd', 15, 2)->nullable();
            $table->decimal('sales_usd', 15, 2)->nullable();
            $table->decimal('annual_sales_usd', 15, 2)->nullable();
            $table->decimal('commission_usd', 15, 2)->nullable();
            $table->decimal('total_commission_usd', 15, 2)->nullable();
            $table->decimal('exchange_rate', 10, 4)->nullable();
            $table->decimal('estimated_euro', 15, 2)->nullable();
            $table->date('estimated_receipt_date')->nullable();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('to_pay_usd', 15, 2)->nullable();
            $table->date('receipt_date')->nullable();
            $table->date('paid_in_date')->nullable();
            $table->decimal('paid_in_brl', 15, 2)->nullable();
            $table->text('incident')->nullable();
            $table->boolean('video_sent')->default(false);
            $table->date('video_date')->nullable();
            $table->string('status')->nullable();
            $table->date('status_date')->nullable();
            $table->date('dhl_date')->nullable();
            $table->string('dhl_number')->nullable();
            $table->date('etd_date')->nullable();
            $table->date('eta_date')->nullable();
            $table->text('observations')->nullable();
            $table->string('shipping_company')->nullable();
            $table->string('container_number')->nullable();
            $table->timestamps();
        });
    }

    private function restoreSupportTables(): void
    {
        foreach (self::SUPPORT_TABLES as $current => $legacy) {
            if (! Schema::hasTable($current) || Schema::hasTable($legacy)) {
                continue;
            }

            $this->dropForeignKeysOn($current, 'contract_id');

            Schema::table($current, function (Blueprint $table) {
                $table->renameColumn('contract_id', 'export_process_id');
            });

            Schema::rename($current, $legacy);

            Schema::table($legacy, function (Blueprint $table) {
                $table->foreign('export_process_id')
                    ->references('id')
                    ->on('export_processes')
                    ->cascadeOnDelete();
            });
        }
    }

    private function restoreFromContracts(): void
    {
        $items = DB::table('contract_items as it')
            ->join('contracts as c', 'c.id', '=', 'it.contract_id')
            ->where('it.position', 1)
            ->select([
                'c.id', 'c.date', 'c.contract_number', 'it.register_number',
                'c.exporter_id', 'c.importer_id', 'it.quantity_tons',
                'it.price_per_ton_usd', 'it.sales_usd', 'it.commission_usd',
                'c.exchange_rate', 'it.estimated_euro', 'c.estimated_receipt_date',
                'c.seller_id', 'it.to_pay_usd', 'c.receipt_date', 'c.paid_in_date',
                'c.paid_in_brl', 'c.incident', 'c.video_sent', 'c.video_date',
                'c.status', 'c.status_date', 'c.dhl_date', 'c.dhl_number',
                'c.etd_date', 'c.eta_date', 'c.observations', 'c.shipping_company',
                'c.container_number', 'c.created_at', 'c.updated_at',
            ]);

        foreach ($items->cursor() as $row) {
            DB::table('export_processes')->insert((array) $row);
        }
    }

    private function buildContractProductMap(): array
    {
        $map = [];

        foreach (DB::table('products')->select('id', 'name')->get() as $product) {
            $normalized = $this->normalizeName($product->name);
            if ($normalized === null) {
                continue;
            }

            $id = DB::table('contract_products')->where('normalized_name', $normalized)->value('id')
                ?? DB::table('contract_products')->insertGetId([
                    'name' => trim($product->name),
                    'normalized_name' => $normalized,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $map[$product->id] = $id;
        }

        return $map;
    }

    private function resolveCountry(?int $importerId, ?int $exporterId): ?string
    {
        foreach ([$importerId, $exporterId] as $clientId) {
            if ($clientId === null) {
                continue;
            }

            $country = DB::table('clients')->where('id', $clientId)->value('country');
            if (filled($country)) {
                return $country;
            }
        }

        return null;
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        return self::STATUS_ALIASES[$status] ?? $status;
    }

    /**
     * Must stay in sync with App\Services\Contracts\ContractProductCatalog::normalize().
     */
    private function normalizeName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($value)));
        $normalized = preg_replace('/[^a-z0-9]+/', '', (string) $normalized);

        return $normalized === '' ? null : $normalized;
    }
};
