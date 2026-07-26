<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_certificate_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('purpose')->nullable();
            $table->string('addressed_to')->nullable();
            $table->string('language', 20)->default('ar');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('certificate_path')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id', 'scr_employee_fk')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->foreign('reviewed_by', 'scr_reviewed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['employee_id', 'status'], 'scr_employee_status_idx');
            $table->index(['status', 'created_at'], 'scr_status_created_idx');
        });

        Schema::create('salary_certificate_request_step_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('salary_certificate_request_id');
            $table->unsignedBigInteger('approval_step_id');
            $table->timestamp('approved_at');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('salary_certificate_request_id', 'scr_step_appr_request_fk')
                ->references('id')
                ->on('salary_certificate_requests')
                ->cascadeOnDelete();
            $table->foreign('approval_step_id', 'scr_step_appr_step_fk')
                ->references('id')
                ->on('leave_approval_steps')
                ->cascadeOnDelete();
            $table->foreign('approved_by', 'scr_step_appr_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(
                ['salary_certificate_request_id', 'approval_step_id'],
                'scr_req_step_approvals_unique'
            );
        });

        Schema::create('salary_certificate_request_approval_rejections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('salary_certificate_request_id');
            $table->unsignedBigInteger('approval_step_id');
            $table->timestamp('rejected_at');
            $table->unsignedBigInteger('rejected_by');
            $table->text('reason');
            $table->unsignedInteger('cleared_approvals_count')->default(0);
            $table->timestamps();

            $table->foreign('salary_certificate_request_id', 'scr_step_rej_request_fk')
                ->references('id')
                ->on('salary_certificate_requests')
                ->cascadeOnDelete();
            $table->foreign('approval_step_id', 'scr_step_rej_step_fk')
                ->references('id')
                ->on('leave_approval_steps')
                ->cascadeOnDelete();
            $table->foreign('rejected_by', 'scr_step_rej_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_certificate_request_approval_rejections');
        Schema::dropIfExists('salary_certificate_request_step_approvals');
        Schema::dropIfExists('salary_certificate_requests');
    }
};
