<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->unsignedInteger('min_notice_days')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('leave_types')->insert([
            [
                'key' => 'annual',
                'name_en' => 'Annual Leave',
                'name_ar' => 'إجازة سنوية',
                'min_notice_days' => 0,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'sick',
                'name_en' => 'Sick Leave',
                'name_ar' => 'إجازة مرضية',
                'min_notice_days' => 0,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'marriage',
                'name_en' => 'Marriage Leave',
                'name_ar' => 'إجازة زواج',
                'min_notice_days' => 0,
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'emergency',
                'name_en' => 'Emergency Leave',
                'name_ar' => 'إجازة طارئة',
                'min_notice_days' => 0,
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'maternity',
                'name_en' => 'Maternity Leave',
                'name_ar' => 'إجازة أمومة',
                'min_notice_days' => 0,
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'bereavement',
                'name_en' => 'Bereavement Leave',
                'name_ar' => 'إجازة وفاة',
                'min_notice_days' => 0,
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
