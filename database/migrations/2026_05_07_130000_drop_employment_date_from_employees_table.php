<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            // Replace legacy index that depended on employment_date.
            try {
                $table->dropIndex(['employment_status', 'employment_date']);
            } catch (\Throwable $e) {
                // Index may already be absent or renamed in some environments.
            }

            if (Schema::hasColumn('employees', 'employment_date')) {
                $table->dropColumn('employment_date');
            }

            $table->index(['employment_status', 'hire_date']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            try {
                $table->dropIndex(['employment_status', 'hire_date']);
            } catch (\Throwable $e) {
                // Ignore if missing.
            }

            if (! Schema::hasColumn('employees', 'employment_date')) {
                $table->date('employment_date')->nullable()->after('hire_date');
            }

            $table->index(['employment_status', 'employment_date']);
        });
    }
};
