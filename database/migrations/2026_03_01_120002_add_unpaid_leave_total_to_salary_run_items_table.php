<?php

declare(strict_types=1);

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
        Schema::table('salary_run_items', function (Blueprint $table) {
            $table->decimal('unpaid_leave_total', 10, 2)->default(0)->after('penalties_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_run_items', function (Blueprint $table) {
            $table->dropColumn('unpaid_leave_total');
        });
    }
};
