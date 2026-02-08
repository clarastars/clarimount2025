<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert existing absent_without_permission rules to absent_without_excuse
        DB::table('labor_law_rules')
            ->where('violation_type', 'absent_without_permission')
            ->update(['violation_type' => 'absent_without_excuse']);

        // Convert existing absent_without_permission penalties to absent_without_excuse
        DB::table('attendance_penalties')
            ->where('violation_type', 'absent_without_permission')
            ->update(['violation_type' => 'absent_without_excuse']);

        // If absent_without_excuse rules don't exist, create them from absent_without_permission template
        $existingRules = DB::table('labor_law_rules')
            ->where('violation_type', 'absent_without_excuse')
            ->count();

        if ($existingRules === 0) {
            // Create default rules for absent_without_excuse
            $rules = [
                [
                    'violation_type' => 'absent_without_excuse',
                    'min_minutes' => null,
                    'max_minutes' => null,
                    'repeat_number' => 1,
                    'action_type' => 'deduction_days',
                    'action_value' => null,
                    'action_value_gross_days' => 1,
                    'action_value_basic_days' => 1,
                    'reason_text' => 'غياب بدون عذر - المرة الأولى',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'violation_type' => 'absent_without_excuse',
                    'min_minutes' => null,
                    'max_minutes' => null,
                    'repeat_number' => 2,
                    'action_type' => 'deduction_days',
                    'action_value' => null,
                    'action_value_gross_days' => 1,
                    'action_value_basic_days' => 1,
                    'reason_text' => 'غياب بدون عذر - المرة الثانية',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'violation_type' => 'absent_without_excuse',
                    'min_minutes' => null,
                    'max_minutes' => null,
                    'repeat_number' => 3,
                    'action_type' => 'deduction_days',
                    'action_value' => null,
                    'action_value_gross_days' => 1,
                    'action_value_basic_days' => 1,
                    'reason_text' => 'غياب بدون عذر - المرة الثالثة',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'violation_type' => 'absent_without_excuse',
                    'min_minutes' => null,
                    'max_minutes' => null,
                    'repeat_number' => 4,
                    'action_type' => 'termination',
                    'action_value' => null,
                    'action_value_gross_days' => null,
                    'action_value_basic_days' => null,
                    'reason_text' => 'إنهاء الخدمة بسبب تكرار الغياب بدون عذر',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

            foreach ($rules as $rule) {
                DB::table('labor_law_rules')->updateOrInsert(
                    [
                        'violation_type' => $rule['violation_type'],
                        'repeat_number' => $rule['repeat_number'],
                    ],
                    $rule
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert back to absent_without_permission
        DB::table('labor_law_rules')
            ->where('violation_type', 'absent_without_excuse')
            ->update(['violation_type' => 'absent_without_permission']);

        DB::table('attendance_penalties')
            ->where('violation_type', 'absent_without_excuse')
            ->update(['violation_type' => 'absent_without_permission']);
    }
};
