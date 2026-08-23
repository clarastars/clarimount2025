<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_entitlement_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('settlement_date');
            $table->string('reason', 500);
            $table->date('last_settlement_date')->nullable();
            $table->unsignedInteger('service_days')->default(0);
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('remaining_leave_days', 10, 2)->default(0);
            $table->decimal('salary_unpaid_days', 10, 2)->default(0);
            $table->decimal('used_annual_leave_days', 10, 2)->default(0);
            $table->decimal('end_of_service_bonus', 12, 2)->default(0);
            $table->decimal('travel_tickets', 12, 2)->default(0);
            $table->decimal('due_commissions', 12, 2)->default(0);
            $table->decimal('salary_dues', 12, 2)->default(0);
            $table->decimal('annual_leave_dues', 12, 2)->default(0);
            $table->decimal('other_dues', 12, 2)->default(0);
            $table->decimal('total_dues', 12, 2)->default(0);
            $table->decimal('advances_deduction', 12, 2)->default(0);
            $table->decimal('custody_deduction', 12, 2)->default(0);
            $table->decimal('excess_leave_deduction', 12, 2)->default(0);
            $table->decimal('social_insurance_deduction', 12, 2)->default(0);
            $table->decimal('used_annual_leave_deduction', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_due', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'settlement_date'], 'emp_entitlement_settlements_emp_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_entitlement_settlements');
    }
};
