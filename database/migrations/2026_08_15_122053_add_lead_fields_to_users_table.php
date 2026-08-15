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
            $table->string('cargo')->nullable()->after('company_name');
            $table->string('import_experience')->nullable()->after('cargo');
            $table->string('import_volume')->nullable()->after('import_experience');
            $table->string('decision_role')->nullable()->after('import_volume');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cargo', 'import_experience', 'import_volume', 'decision_role']);
        });
    }
};
