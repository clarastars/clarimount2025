<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('salary_runs', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('salary_runs', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
