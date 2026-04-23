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
        Schema::table('employee_deductions', function (Blueprint $table): void {
            $table->string('deduction_type', 50)
                ->default('penalties')
                ->after('deduction_date');
        });

        DB::table('employee_deductions')
            ->whereNull('deduction_type')
            ->update(['deduction_type' => 'penalties']);
    }

    public function down(): void
    {
        Schema::table('employee_deductions', function (Blueprint $table): void {
            $table->dropColumn('deduction_type');
        });
    }
};
