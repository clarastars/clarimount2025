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
            $table->timestamp('financial_manager_approved_at')
                ->nullable()
                ->after('director_approved_by');

            $table->foreignId('financial_manager_approved_by')
                ->nullable()
                ->after('financial_manager_approved_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salary_runs', function (Blueprint $table): void {
            $table->dropForeign(['financial_manager_approved_by']);
            $table->dropColumn([
                'financial_manager_approved_at',
                'financial_manager_approved_by',
            ]);
        });
    }
};

