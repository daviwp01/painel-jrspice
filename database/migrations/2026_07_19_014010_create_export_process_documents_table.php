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
        Schema::create('export_process_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_process_id')->constrained('export_processes')->cascadeOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->string('file_type');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_process_documents');
    }
};
