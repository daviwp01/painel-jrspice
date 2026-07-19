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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('company_name')->constrained('clients')->nullOnDelete();
        });

        Schema::table('export_processes', function (Blueprint $table) {
            $table->string('shipping_company')->nullable()->after('status_date');
            $table->string('container_number')->nullable()->after('shipping_company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });

        Schema::table('export_processes', function (Blueprint $table) {
            $table->dropColumn(['shipping_company', 'container_number']);
        });
    }
};
