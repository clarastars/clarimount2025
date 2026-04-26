<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_deductions', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_deductions', 'amount_input_mode')) {
                $table->string('amount_input_mode', 32)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('employee_deductions', 'amount_input_days')) {
                $table->decimal('amount_input_days', 12, 4)->nullable()->after('amount_input_mode');
            }
            if (! Schema::hasColumn('employee_deductions', 'amount_input_percent')) {
                $table->decimal('amount_input_percent', 8, 4)->nullable()->after('amount_input_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_deductions', function (Blueprint $table): void {
            if (Schema::hasColumn('employee_deductions', 'amount_input_percent')) {
                $table->dropColumn('amount_input_percent');
            }
            if (Schema::hasColumn('employee_deductions', 'amount_input_days')) {
                $table->dropColumn('amount_input_days');
            }
            if (Schema::hasColumn('employee_deductions', 'amount_input_mode')) {
                $table->dropColumn('amount_input_mode');
            }
        });
    }
};
