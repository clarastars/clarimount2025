<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table): void {
            $table->string('balance_deduction_mode', 20)->default('employee')->after('allow_past_dates');
            $table->string('pay_mode', 20)->default('employee')->after('balance_deduction_mode');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table): void {
            $table->dropColumn(['balance_deduction_mode', 'pay_mode']);
        });
    }
};
