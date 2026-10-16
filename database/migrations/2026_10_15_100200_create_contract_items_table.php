<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            $table->string('register_number')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('contract_products')->nullOnDelete();

            $table->decimal('quantity_tons', 12, 3)->nullable();
            $table->decimal('price_per_ton_usd', 15, 2)->nullable();
            $table->decimal('sales_usd', 15, 2)->nullable();

            $table->decimal('commission_rate', 8, 4)->nullable();
            $table->decimal('commission_usd', 15, 2)->nullable();

            $table->decimal('exchange_rate', 12, 4)->nullable();
            $table->decimal('estimated_euro', 15, 3)->nullable();

            $table->decimal('to_pay_rate', 8, 4)->nullable();
            $table->decimal('to_pay_usd', 15, 2)->nullable();

            $table->date('etd_date')->nullable();
            $table->date('eta_date')->nullable();
            $table->string('dhl_number')->nullable();
            $table->date('status_date')->nullable();
            $table->date('video_date')->nullable();
            $table->string('video_reference')->nullable();
            $table->text('observations')->nullable();

            $table->unsignedInteger('source_row')->nullable();

            $table->timestamps();

            $table->unique(['contract_id', 'position']);
            $table->index('product_id');
            $table->index('register_number');
            $table->index('source_row');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_items');
    }
};
