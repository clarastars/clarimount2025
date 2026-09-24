<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('amount', 12, 2);
            $table->decimal('monthly_deduction', 12, 2);
            $table->text('reason');
            $table->unsignedInteger('months_count')->default(1);
            $table->json('repayment_schedule')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('employee_debt_id')->nullable();
            $table->timestamps();

            $table->foreign('employee_id', 'adv_req_employee_fk')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->foreign('reviewed_by', 'adv_req_reviewed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('employee_debt_id', 'adv_req_debt_fk')
                ->references('id')
                ->on('employee_debts')
                ->nullOnDelete();

            $table->index(['employee_id', 'status'], 'adv_req_employee_status_idx');
            $table->index(['status', 'created_at'], 'adv_req_status_created_idx');
        });

        Schema::create('advance_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedBigInteger('team_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id', 'adv_step_company_fk')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();
            $table->foreign('team_id', 'adv_step_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();

            $table->index(['company_id', 'sort_order'], 'adv_step_company_order_idx');
        });

        Schema::create('advance_request_step_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advance_request_id');
            $table->unsignedBigInteger('approval_step_id');
            $table->timestamp('approved_at');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('advance_request_id', 'adv_step_appr_request_fk')
                ->references('id')
                ->on('advance_requests')
                ->cascadeOnDelete();
            $table->foreign('approval_step_id', 'adv_step_appr_step_fk')
                ->references('id')
                ->on('advance_approval_steps')
                ->cascadeOnDelete();
            $table->foreign('approved_by', 'adv_step_appr_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(
                ['advance_request_id', 'approval_step_id'],
                'adv_req_step_approvals_unique'
            );
        });

        Schema::create('advance_request_approval_rejections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advance_request_id');
            $table->unsignedBigInteger('approval_step_id');
            $table->timestamp('rejected_at');
            $table->unsignedBigInteger('rejected_by');
            $table->text('reason');
            $table->unsignedInteger('cleared_approvals_count')->default(0);
            $table->timestamps();

            $table->foreign('advance_request_id', 'adv_step_rej_request_fk')
                ->references('id')
                ->on('advance_requests')
                ->cascadeOnDelete();
            $table->foreign('approval_step_id', 'adv_step_rej_step_fk')
                ->references('id')
                ->on('advance_approval_steps')
                ->cascadeOnDelete();
            $table->foreign('rejected_by', 'adv_step_rej_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('employee_debts', function (Blueprint $table) {
            $table->decimal('original_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('monthly_installment', 12, 2)->nullable()->after('original_amount');
            $table->unsignedBigInteger('advance_request_id')->nullable()->after('salary_certificate_request_id');

            $table->foreign('advance_request_id', 'ed_adv_req_fk')
                ->references('id')
                ->on('advance_requests')
                ->nullOnDelete();
            $table->unique('advance_request_id', 'ed_adv_req_unique');
        });

        DB::table('employee_debts')
            ->whereNull('original_amount')
            ->update(['original_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('employee_debts', function (Blueprint $table) {
            $table->dropForeign('ed_adv_req_fk');
            $table->dropUnique('ed_adv_req_unique');
            $table->dropColumn(['advance_request_id', 'monthly_installment', 'original_amount']);
        });

        Schema::dropIfExists('advance_request_approval_rejections');
        Schema::dropIfExists('advance_request_step_approvals');
        Schema::dropIfExists('advance_approval_steps');
        Schema::dropIfExists('advance_requests');
    }
};
