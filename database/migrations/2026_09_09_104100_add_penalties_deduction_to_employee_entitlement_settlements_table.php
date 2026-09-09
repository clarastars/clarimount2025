<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_entitlement_settlements', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_entitlement_settlements', 'penalties_deduction')) {
                $table->decimal('penalties_deduction', 12, 2)
                    ->default(0)
                    ->after('social_insurance_deduction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_entitlement_settlements', function (Blueprint $table): void {
            if (Schema::hasColumn('employee_entitlement_settlements', 'penalties_deduction')) {
                $table->dropColumn('penalties_deduction');
            }
        });
    }
};
