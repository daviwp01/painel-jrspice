<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contract_documents', 'size_bytes')) {
            Schema::table('contract_documents', function (Blueprint $table) {
                $table->unsignedBigInteger('size_bytes')->nullable()->after('file_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_documents', 'size_bytes')) {
            Schema::table('contract_documents', function (Blueprint $table) {
                $table->dropColumn('size_bytes');
            });
        }
    }
};
