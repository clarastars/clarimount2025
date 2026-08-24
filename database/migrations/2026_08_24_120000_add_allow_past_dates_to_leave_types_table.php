<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('leave_types', 'allow_past_dates')) {
                $table->boolean('allow_past_dates')->default(false)->after('min_notice_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table): void {
            if (Schema::hasColumn('leave_types', 'allow_past_dates')) {
                $table->dropColumn('allow_past_dates');
            }
        });
    }
};
