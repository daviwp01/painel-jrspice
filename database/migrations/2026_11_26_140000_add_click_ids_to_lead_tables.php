<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gclid', 255)->nullable()->after('utm_term');
            $table->string('fbclid', 255)->nullable()->after('gclid');
            $table->string('msclkid', 255)->nullable()->after('fbclid');
        });

        Schema::table('external_registration_logs', function (Blueprint $table) {
            $table->string('gclid', 255)->nullable()->after('utm_term');
            $table->string('fbclid', 255)->nullable()->after('gclid');
            $table->string('msclkid', 255)->nullable()->after('fbclid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gclid', 'fbclid', 'msclkid']);
        });

        Schema::table('external_registration_logs', function (Blueprint $table) {
            $table->dropColumn(['gclid', 'fbclid', 'msclkid']);
        });
    }
};
