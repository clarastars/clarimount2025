<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_run_approval_rejections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_run_id')->constrained('salary_runs')->cascadeOnDelete();
            $table->foreignId('approval_step_id')->constrained('salary_run_approval_steps')->cascadeOnDelete();
            $table->timestamp('rejected_at');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->unsignedInteger('cleared_approvals_count')->default(0);
            $table->timestamps();

            $table->index(['salary_run_id', 'rejected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_run_approval_rejections');
    }
};
