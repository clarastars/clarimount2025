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
        Schema::table('employee_entitlement_settlements', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_entitlement_settlements', 'status')) {
                $table->string('status', 20)->default('pending')->after('notes');
            }
            if (! Schema::hasColumn('employee_entitlement_settlements', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('status');
            }
            if (! Schema::hasColumn('employee_entitlement_settlements', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('employee_entitlement_settlements', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_at');
            }
        });

        try {
            Schema::table('employee_entitlement_settlements', function (Blueprint $table) {
                $table->foreign('reviewed_by', 'ees_reviewed_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        } catch (\Throwable) {
            // Foreign key may already exist.
        }

        try {
            Schema::table('employee_entitlement_settlements', function (Blueprint $table) {
                $table->index(['employee_id', 'status'], 'ees_employee_status_idx');
            });
        } catch (\Throwable) {
            // Index may already exist.
        }

        // Existing rows created before this feature are treated as approved.
        DB::table('employee_entitlement_settlements')
            ->whereNull('reviewed_at')
            ->update([
                'status' => 'approved',
                'reviewed_at' => now(),
            ]);

        if (! Schema::hasTable('entitlement_settlement_approval_steps')) {
            Schema::create('entitlement_settlement_approval_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('title');
                $table->unsignedInteger('sort_order');
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'is_active', 'sort_order'], 'esa_steps_company_active_idx');
            });
        }

        if (! Schema::hasTable('entitlement_settlement_step_approvals')) {
            Schema::create('entitlement_settlement_step_approvals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('settlement_id');
                $table->unsignedBigInteger('approval_step_id');
                $table->timestamp('approved_at');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('settlement_id', 'esa_step_appr_settlement_fk')
                    ->references('id')
                    ->on('employee_entitlement_settlements')
                    ->cascadeOnDelete();
                $table->foreign('approval_step_id', 'esa_step_appr_step_fk')
                    ->references('id')
                    ->on('entitlement_settlement_approval_steps')
                    ->cascadeOnDelete();
                $table->unique(['settlement_id', 'approval_step_id'], 'esa_step_appr_unique');
            });
        }

        if (! Schema::hasTable('entitlement_settlement_approval_rejections')) {
            Schema::create('entitlement_settlement_approval_rejections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('settlement_id');
                $table->unsignedBigInteger('approval_step_id');
                $table->timestamp('rejected_at');
                $table->foreignId('rejected_by')->constrained('users')->cascadeOnDelete();
                $table->text('reason');
                $table->unsignedInteger('cleared_approvals_count')->default(0);
                $table->timestamps();

                $table->foreign('settlement_id', 'esa_rej_settlement_fk')
                    ->references('id')
                    ->on('employee_entitlement_settlements')
                    ->cascadeOnDelete();
                $table->foreign('approval_step_id', 'esa_rej_step_fk')
                    ->references('id')
                    ->on('entitlement_settlement_approval_steps')
                    ->cascadeOnDelete();
            });
        }

        $this->seedDefaultStepsForExistingCompanies();
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_settlement_approval_rejections');
        Schema::dropIfExists('entitlement_settlement_step_approvals');
        Schema::dropIfExists('entitlement_settlement_approval_steps');

        Schema::table('employee_entitlement_settlements', function (Blueprint $table) {
            try {
                $table->dropForeign('ees_reviewed_by_fk');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('ees_employee_status_idx');
            } catch (\Throwable) {
            }

            foreach (['review_notes', 'reviewed_at', 'reviewed_by', 'status'] as $column) {
                if (Schema::hasColumn('employee_entitlement_settlements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function seedDefaultStepsForExistingCompanies(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $defaults = [
            ['title' => 'مراجعة الموارد البشرية', 'sort_order' => 1, 'team_name' => 'الموارد البشرية'],
            ['title' => 'اعتماد المدير المباشر', 'sort_order' => 2, 'team_name' => null],
            ['title' => 'اعتماد الإدارة المالية', 'sort_order' => 3, 'team_name' => null],
        ];

        $now = now();
        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            if (DB::table('entitlement_settlement_approval_steps')->where('company_id', $companyId)->exists()) {
                continue;
            }

            foreach ($defaults as $step) {
                $teamId = null;
                if ($step['team_name']) {
                    $teamId = DB::table('teams')->where('name', $step['team_name'])->value('id');
                }

                DB::table('entitlement_settlement_approval_steps')->insert([
                    'company_id' => $companyId,
                    'title' => $step['title'],
                    'sort_order' => $step['sort_order'],
                    'team_id' => $teamId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
