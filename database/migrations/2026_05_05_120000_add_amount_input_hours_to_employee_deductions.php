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
            if (! Schema::hasColumn('employee_deductions', 'amount_input_hours')) {
                $table->decimal('amount_input_hours', 12, 4)->nullable()->after('amount_input_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_deductions', function (Blueprint $table): void {
            if (Schema::hasColumn('employee_deductions', 'amount_input_hours')) {
                $table->dropColumn('amount_input_hours');
            }
        });
    }
};
