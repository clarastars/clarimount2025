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
        // Add fields to labor_law_rules
        Schema::table('labor_law_rules', function (Blueprint $table) {
            $table->integer('action_value_gross_days')->nullable()->after('action_value');
            $table->integer('action_value_basic_days')->nullable()->after('action_value_gross_days');
        });

        // Add fields to attendance_penalties
        Schema::table('attendance_penalties', function (Blueprint $table) {
            $table->integer('action_value_gross_days')->nullable()->after('action_value');
            $table->integer('action_value_basic_days')->nullable()->after('action_value_gross_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labor_law_rules', function (Blueprint $table) {
            $table->dropColumn(['action_value_gross_days', 'action_value_basic_days']);
        });

        Schema::table('attendance_penalties', function (Blueprint $table) {
            $table->dropColumn(['action_value_gross_days', 'action_value_basic_days']);
        });
    }
};
