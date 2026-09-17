<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_days_used_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename')->nullable();
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('rows_processed')->default(0);
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('leave_days_used_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('leave_days_used_import_id')
                ->constrained('leave_days_used_imports')
                ->cascadeOnDelete();
            $table->unsignedInteger('excel_row')->nullable();
            $table->string('id_number')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('days_added', 10, 2)->nullable();
            $table->decimal('previous_value', 10, 2)->nullable();
            $table->decimal('new_value', 10, 2)->nullable();
            $table->string('status', 20);
            $table->string('message')->nullable();
            $table->timestamps();

            $table->index(['leave_days_used_import_id', 'status'], 'ldu_import_rows_import_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_days_used_import_rows');
        Schema::dropIfExists('leave_days_used_imports');
    }
};
