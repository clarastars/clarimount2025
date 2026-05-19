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
        Schema::create('salary_run_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('salary_run_step_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_run_id')->constrained('salary_runs')->cascadeOnDelete();
            $table->foreignId('approval_step_id')->constrained('salary_run_approval_steps')->cascadeOnDelete();
            $table->timestamp('approved_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['salary_run_id', 'approval_step_id']);
        });

        $this->seedDefaultSteps();
        $this->migrateLegacyApprovals();
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_run_step_approvals');
        Schema::dropIfExists('salary_run_approval_steps');
    }

    private function seedDefaultSteps(): void
    {
        $defaults = [
            ['title' => 'اعتماد الموارد البشرية', 'sort_order' => 1, 'team_name' => 'الموارد البشرية'],
            ['title' => 'اعتماد المحاسب', 'sort_order' => 2, 'team_name' => 'المحاسبين'],
            ['title' => 'اعتماد المدير المالي', 'sort_order' => 3, 'team_name' => null],
            ['title' => 'اعتماد المدير التنفيذي', 'sort_order' => 4, 'team_name' => null],
            ['title' => 'اعتماد المدير العام', 'sort_order' => 5, 'team_name' => null],
        ];

        $now = now();
        foreach ($defaults as $step) {
            $teamId = null;
            if ($step['team_name']) {
                $teamId = DB::table('teams')->where('name', $step['team_name'])->value('id');
            }

            DB::table('salary_run_approval_steps')->insert([
                'title' => $step['title'],
                'sort_order' => $step['sort_order'],
                'team_id' => $teamId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function migrateLegacyApprovals(): void
    {
        if (! Schema::hasTable('salary_runs')) {
            return;
        }

        $legacyMap = [
            'hr_approved_at' => 1,
            'accountant_approved_at' => 2,
            'financial_manager_approved_at' => 3,
            'director_approved_at' => 4,
            'ceo_approved_at' => 5,
        ];

        $stepIds = DB::table('salary_run_approval_steps')
            ->orderBy('sort_order')
            ->pluck('id', 'sort_order');

        $runs = DB::table('salary_runs')->get(['id', 'hr_approved_at', 'hr_approved_by', 'accountant_approved_at', 'accountant_approved_by', 'financial_manager_approved_at', 'financial_manager_approved_by', 'director_approved_at', 'director_approved_by', 'ceo_approved_at', 'ceo_approved_by']);

        foreach ($runs as $run) {
            foreach ($legacyMap as $atColumn => $sortOrder) {
                $byColumn = str_replace('_at', '_by', $atColumn);
                $approvedAt = $run->{$atColumn} ?? null;
                if (! $approvedAt) {
                    continue;
                }

                $stepId = $stepIds[$sortOrder] ?? null;
                if (! $stepId) {
                    continue;
                }

                DB::table('salary_run_step_approvals')->updateOrInsert(
                    [
                        'salary_run_id' => $run->id,
                        'approval_step_id' => $stepId,
                    ],
                    [
                        'approved_at' => $approvedAt,
                        'approved_by' => $run->{$byColumn},
                        'created_at' => $approvedAt,
                        'updated_at' => $approvedAt,
                    ]
                );
            }
        }
    }
};
