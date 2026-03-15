<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_deductions')) {
            return;
        }

        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->date('deduction_date');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['employee_id', 'deduction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_deductions');
    }
};
