<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_penalties', function (Blueprint $table): void {
            $table->dropUnique('attendance_penalties_unique');
            $table->unique(['employee_id', 'attendance_date', 'violation_type'], 'attendance_penalties_emp_date_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_penalties', function (Blueprint $table): void {
            $table->dropUnique('attendance_penalties_emp_date_type_unique');
            $table->unique(['employee_id', 'attendance_date'], 'attendance_penalties_unique');
        });
    }
};
