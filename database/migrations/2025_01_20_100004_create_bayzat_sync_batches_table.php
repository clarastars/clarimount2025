<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bayzat_sync_batches')) {
            return;
        }

        Schema::create('bayzat_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('attendance_import_id')->constrained()->onDelete('cascade');
            $table->integer('total_records')->default(0);
            $table->integer('synced_records')->default(0);
            $table->integer('failed_records')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['company_id', 'status'], 'idx_company_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bayzat_sync_batches');
    }
};
