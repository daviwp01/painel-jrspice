<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filter_type', 50);   // country, product, supplier, date_range, page
            $table->string('filter_value', 255);  // Brazil, Garlic, 2026-15, Todos...
            $table->string('page_context', 100)->nullable(); // Dashboard/Show, Dashboard/HistoricalData...
            $table->timestamp('searched_at');

            $table->index(['user_id', 'filter_type']);
            $table->index(['user_id', 'searched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_search_logs');
    }
};
