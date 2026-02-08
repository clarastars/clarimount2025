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
        // Insert absence violation rules (using absent_without_excuse as unified type)
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

        // Insert only if they don't exist
        foreach ($rules as $rule) {
            $exists = DB::table('labor_law_rules')
                ->where('violation_type', $rule['violation_type'])
                ->where('repeat_number', $rule['repeat_number'])
                ->exists();
            
            if (!$exists) {
                DB::table('labor_law_rules')->insert($rule);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove absence violation rules
        DB::table('labor_law_rules')
            ->where('violation_type', 'absent_without_excuse')
            ->delete();
    }
};
