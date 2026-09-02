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
        Schema::table('salary_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_runs', 'run_type')) {
                $table->string('run_type', 20)->default('regular')->after('month');
            }

            if (! Schema::hasColumn('salary_runs', 'sequence')) {
                $table->unsignedSmallInteger('sequence')->default(1)->after('run_type');
            }

            if (! Schema::hasColumn('salary_runs', 'label')) {
                $table->string('label')->nullable()->after('sequence');
            }
        });

        DB::table('salary_runs')
            ->where('sequence', '<', 1)
            ->update(['sequence' => 1]);

        $hasOldUnique = collect(DB::select('SHOW INDEX FROM salary_runs'))
            ->contains(fn (object $index): bool => $index->Key_name === 'salary_runs_company_id_year_month_unique');

        if ($hasOldUnique) {
            Schema::table('salary_runs', function (Blueprint $table) {
                $table->index('company_id', 'salary_runs_company_id_index');
            });

            Schema::table('salary_runs', function (Blueprint $table) {
                $table->dropUnique('salary_runs_company_id_year_month_unique');
            });
        }

        $hasNewUnique = collect(DB::select('SHOW INDEX FROM salary_runs'))
            ->contains(fn (object $index): bool => $index->Key_name === 'salary_runs_company_period_sequence_unique');

        if (! $hasNewUnique) {
            Schema::table('salary_runs', function (Blueprint $table) {
                $table->unique(['company_id', 'year', 'month', 'sequence'], 'salary_runs_company_period_sequence_unique');
            });
        }

        Schema::table('salary_run_items', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_run_items', 'period_start')) {
                $table->date('period_start')->nullable()->after('employee_id');
            }

            if (! Schema::hasColumn('salary_run_items', 'period_end')) {
                $table->date('period_end')->nullable()->after('period_start');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_run_items', function (Blueprint $table) {
            if (Schema::hasColumn('salary_run_items', 'period_start')) {
                $table->dropColumn('period_start');
            }

            if (Schema::hasColumn('salary_run_items', 'period_end')) {
                $table->dropColumn('period_end');
            }
        });

        $hasNewUnique = collect(DB::select('SHOW INDEX FROM salary_runs'))
            ->contains(fn (object $index): bool => $index->Key_name === 'salary_runs_company_period_sequence_unique');

        if ($hasNewUnique) {
            Schema::table('salary_runs', function (Blueprint $table) {
                $table->dropUnique('salary_runs_company_period_sequence_unique');
            });
        }

        $hasOldUnique = collect(DB::select('SHOW INDEX FROM salary_runs'))
            ->contains(fn (object $index): bool => $index->Key_name === 'salary_runs_company_id_year_month_unique');

        if (! $hasOldUnique) {
            Schema::table('salary_runs', function (Blueprint $table) {
                $table->unique(['company_id', 'year', 'month']);
            });
        }

        Schema::table('salary_runs', function (Blueprint $table) {
            if (Schema::hasColumn('salary_runs', 'run_type')) {
                $table->dropColumn('run_type');
            }

            if (Schema::hasColumn('salary_runs', 'sequence')) {
                $table->dropColumn('sequence');
            }

            if (Schema::hasColumn('salary_runs', 'label')) {
                $table->dropColumn('label');
            }
        });

        $hasCompanyIndex = collect(DB::select('SHOW INDEX FROM salary_runs'))
            ->contains(fn (object $index): bool => $index->Key_name === 'salary_runs_company_id_index');

        if ($hasCompanyIndex) {
            Schema::table('salary_runs', function (Blueprint $table) {
                $table->dropIndex('salary_runs_company_id_index');
            });
        }
    }
};
