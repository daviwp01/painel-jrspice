<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('export_processes', function (Blueprint $table) {
            $table->id();
            
            // General Info
            $table->date('date')->nullable();
            $table->string('contract_number')->nullable();
            $table->string('register_number')->nullable();
            $table->foreignId('exporter_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('importer_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            
            // Financials
            $table->decimal('quantity_tons', 10, 2)->nullable();
            $table->decimal('price_per_ton_usd', 15, 2)->nullable();
            $table->decimal('sales_usd', 15, 2)->nullable(); // Calculated
            $table->decimal('annual_sales_usd', 15, 2)->nullable();
            $table->decimal('commission_usd', 15, 2)->nullable();
            $table->decimal('total_commission_usd', 15, 2)->nullable();
            $table->decimal('exchange_rate', 10, 4)->nullable();
            $table->decimal('estimated_euro', 15, 2)->nullable();
            
            // Payment & Internal
            $table->date('estimated_receipt_date')->nullable();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('to_pay_usd', 15, 2)->nullable();
            $table->date('receipt_date')->nullable();
            $table->date('paid_in_date')->nullable();
            $table->decimal('paid_in_brl', 15, 2)->nullable();
            
            // Logistics & Status
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
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_processes');
    }
};
