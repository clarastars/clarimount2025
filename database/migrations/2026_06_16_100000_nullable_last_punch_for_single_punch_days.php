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
        Schema::table('zk_daily_attendance', function (Blueprint $table) {
            $table->dateTime('last_punch')->nullable()->change();
        });

        DB::table('zk_daily_attendance')
            ->where('punch_count', '<=', 1)
            ->update([
                'last_punch' => null,
                'last_verify_mode' => null,
            ]);

        DB::table('attendance_daily_presentations')
            ->where('punch_count', '<=', 1)
            ->update([
                'last_punch' => null,
                'last_verify_mode' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('zk_daily_attendance')
            ->whereNull('last_punch')
            ->whereNotNull('first_punch')
            ->update([
                'last_punch' => DB::raw('first_punch'),
            ]);

        Schema::table('zk_daily_attendance', function (Blueprint $table) {
            $table->dateTime('last_punch')->nullable(false)->change();
        });

        DB::table('attendance_daily_presentations')
            ->whereNull('last_punch')
            ->whereNotNull('first_punch')
            ->update([
                'last_punch' => DB::raw('first_punch'),
            ]);
    }
};
