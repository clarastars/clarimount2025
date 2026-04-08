<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_daily_presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('att_date');
            $table->string('status_ar', 32);
            $table->unsignedSmallInteger('late_minutes')->nullable();
            $table->boolean('is_virtual_absence')->default(false);
            $table->foreignId('zk_daily_attendance_id')->nullable()->constrained('zk_daily_attendance')->nullOnDelete();
            $table->dateTime('first_punch')->nullable();
            $table->dateTime('last_punch')->nullable();
            $table->unsignedInteger('punch_count')->default(0);
            $table->tinyInteger('first_verify_mode')->nullable();
            $table->tinyInteger('last_verify_mode')->nullable();
            $table->string('device_pin', 64)->nullable();
            $table->string('device_name')->nullable();
            $table->string('serial_number', 128)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'att_date']);
            $table->index(['company_id', 'att_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_daily_presentations');
    }
};
