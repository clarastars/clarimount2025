<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * خصم دقائق التأخير: مبلغ يُحسب من (دقائق التأخير × سعر الدقيقة) ويُعتمد/يُرفض مع الجزاء.
     */
    public function up(): void
    {
        Schema::table('attendance_penalties', function (Blueprint $table) {
            $table->decimal('late_minutes_deduction_amount', 12, 2)->nullable()->after('reason_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_penalties', function (Blueprint $table) {
            $table->dropColumn('late_minutes_deduction_amount');
        });
    }
};
