<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            $table->date('date')->nullable();
            $table->string('contract_number')->nullable();
            $table->foreignId('exporter_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('importer_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('country')->nullable();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->nullable();
            $table->date('status_date')->nullable();

            $table->date('dhl_date')->nullable();
            $table->string('dhl_number')->nullable();
            $table->date('etd_date')->nullable();
            $table->date('eta_date')->nullable();

            $table->boolean('video_sent')->default(false);
            $table->string('video_reference')->nullable();
            $table->date('video_date')->nullable();

            $table->text('incident')->nullable();
            $table->date('estimated_receipt_date')->nullable();
            $table->decimal('exchange_rate', 12, 4)->nullable();
            $table->date('receipt_date')->nullable();
            $table->date('paid_in_date')->nullable();
            $table->decimal('paid_in_brl', 15, 2)->nullable();

            $table->text('observations')->nullable();
            $table->string('shipping_company')->nullable();
            $table->string('container_number')->nullable();

            $table->unsignedInteger('source_row')->nullable();
            $table->boolean('has_incident')->default(false);

            $table->timestamps();

            $table->unique('source_row');
            $table->index('contract_number');
            $table->index('status');
            $table->index('date');
            $table->index('exporter_id');
            $table->index('importer_id');
            $table->index('seller_id');
            $table->index('country');
            $table->index(['status', 'eta_date']);
            $table->index(['importer_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
