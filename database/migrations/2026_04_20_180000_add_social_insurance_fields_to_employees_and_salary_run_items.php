<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('social_insurance_deduction_rate', 5, 2)
                ->nullable()
                ->after('allowance_personal_car');
        });

        Schema::table('salary_run_items', function (Blueprint $table) {
            $table->decimal('social_insurance_deduction_total', 10, 2)
                ->default(0)
                ->after('penalties_total');
        });
    }

    public function down(): void
    {
        Schema::table('salary_run_items', function (Blueprint $table) {
            $table->dropColumn('social_insurance_deduction_total');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('social_insurance_deduction_rate');
        });
    }
};
