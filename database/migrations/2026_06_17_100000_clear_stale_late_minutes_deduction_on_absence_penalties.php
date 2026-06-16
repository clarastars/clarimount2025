<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendance_penalties')
            ->where('violation_type', 'absent_without_excuse')
            ->whereNotNull('late_minutes_deduction_amount')
            ->update(['late_minutes_deduction_amount' => null]);

        DB::table('attendance_penalties')
            ->where('late_minutes', '<=', 0)
            ->whereNotNull('late_minutes_deduction_amount')
            ->update(['late_minutes_deduction_amount' => null]);
    }

    public function down(): void
    {
        // Historical cleanup — cannot restore previous stale amounts.
    }
};
