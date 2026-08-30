<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_run_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('salary_run_items', 'export_snapshot')) {
                $table->json('export_snapshot')->nullable()->after('debt_deductions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_run_items', function (Blueprint $table): void {
            if (Schema::hasColumn('salary_run_items', 'export_snapshot')) {
                $table->dropColumn('export_snapshot');
            }
        });
    }
};
