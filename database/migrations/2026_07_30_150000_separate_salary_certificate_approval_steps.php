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
        if (! Schema::hasTable('salary_certificate_approval_steps')) {
            Schema::create('salary_certificate_approval_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->unsignedInteger('sort_order')->default(1);
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'sort_order']);
            });
        }

        $idMap = [];
        $now = now();

        $leaveSteps = DB::table('leave_approval_steps')->orderBy('id')->get();
        foreach ($leaveSteps as $step) {
            $newId = DB::table('salary_certificate_approval_steps')->insertGetId([
                'company_id' => $step->company_id,
                'title' => $step->title,
                'sort_order' => $step->sort_order,
                'team_id' => $step->team_id,
                'is_active' => $step->is_active,
                'created_at' => $step->created_at ?? $now,
                'updated_at' => $step->updated_at ?? $now,
            ]);

            $idMap[(int) $step->id] = $newId;
        }

        $this->dropForeignIfExists('salary_certificate_request_step_approvals', 'scr_step_appr_step_fk');
        $this->dropForeignIfExists('salary_certificate_request_approval_rejections', 'scr_step_rej_step_fk');

        if ($idMap !== []) {
            foreach ($idMap as $oldId => $newId) {
                DB::table('salary_certificate_request_step_approvals')
                    ->where('approval_step_id', $oldId)
                    ->update(['approval_step_id' => $newId]);

                DB::table('salary_certificate_request_approval_rejections')
                    ->where('approval_step_id', $oldId)
                    ->update(['approval_step_id' => $newId]);
            }
        }

        Schema::table('salary_certificate_request_step_approvals', function (Blueprint $table): void {
            $table->foreign('approval_step_id', 'scr_step_appr_step_fk')
                ->references('id')
                ->on('salary_certificate_approval_steps')
                ->cascadeOnDelete();
        });

        Schema::table('salary_certificate_request_approval_rejections', function (Blueprint $table): void {
            $table->foreign('approval_step_id', 'scr_step_rej_step_fk')
                ->references('id')
                ->on('salary_certificate_approval_steps')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignIfExists('salary_certificate_request_step_approvals', 'scr_step_appr_step_fk');
        $this->dropForeignIfExists('salary_certificate_request_approval_rejections', 'scr_step_rej_step_fk');

        // Best-effort remap: match by company + title + sort_order back to leave steps.
        $salarySteps = DB::table('salary_certificate_approval_steps')->get();
        foreach ($salarySteps as $salaryStep) {
            $leaveStepId = DB::table('leave_approval_steps')
                ->where('company_id', $salaryStep->company_id)
                ->where('title', $salaryStep->title)
                ->where('sort_order', $salaryStep->sort_order)
                ->value('id');

            if ($leaveStepId === null) {
                continue;
            }

            DB::table('salary_certificate_request_step_approvals')
                ->where('approval_step_id', $salaryStep->id)
                ->update(['approval_step_id' => $leaveStepId]);

            DB::table('salary_certificate_request_approval_rejections')
                ->where('approval_step_id', $salaryStep->id)
                ->update(['approval_step_id' => $leaveStepId]);
        }

        Schema::table('salary_certificate_request_step_approvals', function (Blueprint $table): void {
            $table->foreign('approval_step_id', 'scr_step_appr_step_fk')
                ->references('id')
                ->on('leave_approval_steps')
                ->cascadeOnDelete();
        });

        Schema::table('salary_certificate_request_approval_rejections', function (Blueprint $table): void {
            $table->foreign('approval_step_id', 'scr_step_rej_step_fk')
                ->references('id')
                ->on('leave_approval_steps')
                ->cascadeOnDelete();
        });

        Schema::dropIfExists('salary_certificate_approval_steps');
    }

    private function dropForeignIfExists(string $table, string $foreignName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $database = Schema::getConnection()->getDatabaseName();
        $exists = DB::table('information_schema.table_constraints')
            ->where('constraint_schema', $database)
            ->where('table_name', $table)
            ->where('constraint_name', $foreignName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();

        if (! $exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($foreignName): void {
            $blueprint->dropForeign($foreignName);
        });
    }
};
