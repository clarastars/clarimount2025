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
        Schema::table('salary_run_approval_steps', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->cascadeOnDelete();
        });

        $globalSteps = DB::table('salary_run_approval_steps')
            ->whereNull('company_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($globalSteps->isEmpty()) {
            $this->makeCompanyIdRequired();

            return;
        }

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $this->copyStepsForCompany((int) $companyId, $globalSteps);
        }

        DB::table('salary_run_approval_steps')
            ->whereIn('id', $globalSteps->pluck('id'))
            ->delete();

        $this->makeCompanyIdRequired();
    }

    public function down(): void
    {
        Schema::table('salary_run_approval_steps', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $globalSteps
     */
    private function copyStepsForCompany(int $companyId, $globalSteps): void
    {
        $idMap = [];
        $now = now();

        foreach ($globalSteps as $step) {
            $newId = DB::table('salary_run_approval_steps')->insertGetId([
                'company_id' => $companyId,
                'title' => $step->title,
                'sort_order' => $step->sort_order,
                'team_id' => $step->team_id,
                'is_active' => $step->is_active,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $idMap[(int) $step->id] = $newId;
        }

        $runIds = DB::table('salary_runs')
            ->where('company_id', $companyId)
            ->pluck('id');

        if ($runIds->isEmpty()) {
            return;
        }

        $approvals = DB::table('salary_run_step_approvals')
            ->whereIn('salary_run_id', $runIds)
            ->get(['id', 'approval_step_id']);

        foreach ($approvals as $approval) {
            $newStepId = $idMap[(int) $approval->approval_step_id] ?? null;

            if ($newStepId !== null) {
                DB::table('salary_run_step_approvals')
                    ->where('id', $approval->id)
                    ->update(['approval_step_id' => $newStepId]);
            }
        }

        if (! Schema::hasTable('salary_run_approval_rejections')) {
            return;
        }

        $rejections = DB::table('salary_run_approval_rejections')
            ->whereIn('salary_run_id', $runIds)
            ->get(['id', 'approval_step_id']);

        foreach ($rejections as $rejection) {
            $newStepId = $idMap[(int) $rejection->approval_step_id] ?? null;

            if ($newStepId !== null) {
                DB::table('salary_run_approval_rejections')
                    ->where('id', $rejection->id)
                    ->update(['approval_step_id' => $newStepId]);
            }
        }
    }

    private function makeCompanyIdRequired(): void
    {
        Schema::table('salary_run_approval_steps', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->index(['company_id', 'is_active', 'sort_order']);
        });
    }
};
