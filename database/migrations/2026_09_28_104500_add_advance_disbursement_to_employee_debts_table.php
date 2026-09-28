<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_debts', function (Blueprint $table): void {
            $table->boolean('pays_out_via_salary_run')->default(false)->after('advance_request_id');
            $table->foreignId('advance_disbursement_salary_run_id')
                ->nullable()
                ->after('pays_out_via_salary_run')
                ->constrained('salary_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_debts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('advance_disbursement_salary_run_id');
            $table->dropColumn('pays_out_via_salary_run');
        });
    }
};
