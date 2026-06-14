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
        Schema::create('leave_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order');
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::create('leave_request_step_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('approval_step_id')->constrained('leave_approval_steps')->cascadeOnDelete();
            $table->timestamp('approved_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['leave_request_id', 'approval_step_id'], 'leave_req_step_approvals_unique');
        });

        Schema::create('leave_request_approval_rejections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('approval_step_id')->constrained('leave_approval_steps')->cascadeOnDelete();
            $table->timestamp('rejected_at');
            $table->foreignId('rejected_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->unsignedInteger('cleared_approvals_count')->default(0);
            $table->timestamps();
        });

        $this->seedDefaultStepsForExistingCompanies();
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_approval_rejections');
        Schema::dropIfExists('leave_request_step_approvals');
        Schema::dropIfExists('leave_approval_steps');
    }

    private function seedDefaultStepsForExistingCompanies(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $defaults = [
            ['title' => 'مراجعة الموارد البشرية', 'sort_order' => 1, 'team_name' => 'الموارد البشرية'],
            ['title' => 'اعتماد المدير المباشر', 'sort_order' => 2, 'team_name' => null],
            ['title' => 'اعتماد الإدارة', 'sort_order' => 3, 'team_name' => null],
        ];

        $now = now();
        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            if (DB::table('leave_approval_steps')->where('company_id', $companyId)->exists()) {
                continue;
            }

            foreach ($defaults as $step) {
                $teamId = null;
                if ($step['team_name']) {
                    $teamId = DB::table('teams')->where('name', $step['team_name'])->value('id');
                }

                DB::table('leave_approval_steps')->insert([
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
