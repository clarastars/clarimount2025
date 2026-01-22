<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('salary_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_run_id')->constrained('salary_runs')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('allowances', 10, 2);
            $table->decimal('gross_salary', 10, 2);
            $table->decimal('penalties_total', 10, 2)->default(0);
            $table->decimal('net_salary', 10, 2);
            $table->json('breakdown')->nullable();
            $table->timestamps();
            
            $table->unique(['salary_run_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_run_items');
    }
};
