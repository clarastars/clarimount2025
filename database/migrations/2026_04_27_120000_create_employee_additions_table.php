<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_additions')) {
            return;
        }

        Schema::create('employee_additions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->string('amount_input_mode', 32)->nullable();
            $table->decimal('amount_input_days', 12, 4)->nullable();
            $table->decimal('amount_input_percent', 8, 4)->nullable();
            $table->date('addition_date');
            $table->string('addition_type', 64);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['employee_id', 'addition_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_additions');
    }
};

