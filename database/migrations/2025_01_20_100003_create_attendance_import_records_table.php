<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_import_records')) {
            return;
        }

        Schema::create('attendance_import_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_import_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('csv_employee_id');
            $table->string('first_name');
            $table->string('fingerprint_department');
            $table->date('date');
            $table->string('weekday', 20);
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->decimal('work_duration', 5, 2)->nullable();
            $table->decimal('break_duration', 5, 2)->nullable();
            $table->decimal('overtime_duration', 5, 2)->nullable();
            $table->enum('bayzat_sync_status', ['pending', 'syncing', 'synced', 'failed', 'skipped'])->default('pending');
            $table->timestamp('bayzat_sync_at')->nullable();
            $table->text('bayzat_sync_error')->nullable();
            $table->integer('bayzat_retry_count')->default(0);
            $table->timestamp('bayzat_next_retry_at')->nullable();
            $table->json('validation_errors')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->timestamps();
            
            $table->index(['attendance_import_id', 'date'], 'idx_import_date');
            $table->index(['csv_employee_id', 'date'], 'idx_employee_date');
            $table->index(['bayzat_sync_status', 'bayzat_next_retry_at'], 'idx_bayzat_sync');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_import_records');
    }
};
