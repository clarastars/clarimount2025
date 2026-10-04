<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offboarding_checklist_item_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('attachment_mode', 20)->default('optional'); // none|optional|required
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'sort_order'], 'off_tpl_company_active_idx');
        });

        Schema::create('offboarding_item_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('template_id');
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(1);
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('template_id', 'off_item_step_tpl_fk')
                ->references('id')
                ->on('offboarding_checklist_item_templates')
                ->cascadeOnDelete();
            $table->index(['template_id', 'is_active', 'sort_order'], 'off_item_steps_tpl_active_idx');
            $table->index(['company_id', 'is_active', 'sort_order'], 'off_item_steps_company_active_idx');
        });

        Schema::create('offboarding_clearance_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(1);
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'sort_order'], 'off_clr_steps_company_active_idx');
        });

        Schema::create('employee_offboarding_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('status', 30)->default('in_progress');
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->date('termination_date')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->foreign('started_by', 'off_case_started_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by', 'off_case_reviewed_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['employee_id', 'status'], 'off_cases_employee_status_idx');
            $table->index(['company_id', 'status'], 'off_cases_company_status_idx');
        });

        Schema::create('employee_offboarding_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offboarding_case_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('title');
            $table->string('attachment_mode', 20)->default('optional');
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('status', 20)->default('pending');
            $table->string('attachment_path')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('offboarding_case_id', 'off_items_case_fk')
                ->references('id')->on('employee_offboarding_cases')->cascadeOnDelete();
            $table->foreign('template_id', 'off_items_tpl_fk')
                ->references('id')->on('offboarding_checklist_item_templates')->nullOnDelete();
            $table->foreign('completed_by', 'off_items_completed_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['offboarding_case_id', 'status'], 'off_items_case_status_idx');
        });

        Schema::create('employee_offboarding_item_step_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offboarding_item_id');
            $table->unsignedBigInteger('approval_step_id');
            $table->timestamp('approved_at');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('offboarding_item_id', 'off_item_appr_item_fk')
                ->references('id')->on('employee_offboarding_items')->cascadeOnDelete();
            $table->foreign('approval_step_id', 'off_item_appr_step_fk')
                ->references('id')->on('offboarding_item_approval_steps')->cascadeOnDelete();
            $table->foreign('approved_by', 'off_item_appr_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(['offboarding_item_id', 'approval_step_id'], 'off_item_appr_unique');
        });

        Schema::create('employee_offboarding_item_approval_rejections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offboarding_item_id');
            $table->unsignedBigInteger('approval_step_id');
            $table->timestamp('rejected_at');
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('reason');
            $table->unsignedInteger('cleared_approvals_count')->default(0);
            $table->timestamps();

            $table->foreign('offboarding_item_id', 'off_item_rej_item_fk')
                ->references('id')->on('employee_offboarding_items')->cascadeOnDelete();
            $table->foreign('approval_step_id', 'off_item_rej_step_fk')
                ->references('id')->on('offboarding_item_approval_steps')->cascadeOnDelete();
            $table->foreign('rejected_by', 'off_item_rej_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('employee_offboarding_clearance_step_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offboarding_case_id');
            $table->unsignedBigInteger('approval_step_id');
            $table->timestamp('approved_at');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('offboarding_case_id', 'off_clr_appr_case_fk')
                ->references('id')->on('employee_offboarding_cases')->cascadeOnDelete();
            $table->foreign('approval_step_id', 'off_clr_appr_step_fk')
                ->references('id')->on('offboarding_clearance_approval_steps')->cascadeOnDelete();
            $table->foreign('approved_by', 'off_clr_appr_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(['offboarding_case_id', 'approval_step_id'], 'off_clr_appr_unique');
        });

        Schema::create('employee_offboarding_clearance_approval_rejections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offboarding_case_id');
            $table->unsignedBigInteger('approval_step_id');
            $table->timestamp('rejected_at');
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('reason');
            $table->unsignedInteger('cleared_approvals_count')->default(0);
            $table->timestamps();

            $table->foreign('offboarding_case_id', 'off_clr_rej_case_fk')
                ->references('id')->on('employee_offboarding_cases')->cascadeOnDelete();
            $table->foreign('approval_step_id', 'off_clr_rej_step_fk')
                ->references('id')->on('offboarding_clearance_approval_steps')->cascadeOnDelete();
            $table->foreign('rejected_by', 'off_clr_rej_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_offboarding_clearance_approval_rejections');
        Schema::dropIfExists('employee_offboarding_clearance_step_approvals');
        Schema::dropIfExists('employee_offboarding_item_approval_rejections');
        Schema::dropIfExists('employee_offboarding_item_step_approvals');
        Schema::dropIfExists('employee_offboarding_items');
        Schema::dropIfExists('employee_offboarding_cases');
        Schema::dropIfExists('offboarding_clearance_approval_steps');
        Schema::dropIfExists('offboarding_item_approval_steps');
        Schema::dropIfExists('offboarding_checklist_item_templates');
    }
};
