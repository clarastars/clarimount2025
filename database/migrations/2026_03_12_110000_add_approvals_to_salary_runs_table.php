<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_runs', function (Blueprint $table) {
            $table->timestamp('hr_approved_at')->nullable()->after('created_by');
            $table->foreignId('hr_approved_by')->nullable()->after('hr_approved_at')->constrained('users')->onDelete('set null');
            $table->timestamp('director_approved_at')->nullable()->after('hr_approved_by');
            $table->foreignId('director_approved_by')->nullable()->after('director_approved_at')->constrained('users')->onDelete('set null');
            $table->timestamp('accountant_approved_at')->nullable()->after('director_approved_by');
            $table->foreignId('accountant_approved_by')->nullable()->after('accountant_approved_at')->constrained('users')->onDelete('set null');
            $table->timestamp('ceo_approved_at')->nullable()->after('accountant_approved_by');
            $table->foreignId('ceo_approved_by')->nullable()->after('ceo_approved_at')->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('salary_runs', function (Blueprint $table) {
            $table->dropForeign(['hr_approved_by']);
            $table->dropForeign(['director_approved_by']);
            $table->dropForeign(['accountant_approved_by']);
            $table->dropForeign(['ceo_approved_by']);
            $table->dropColumn([
                'hr_approved_at', 'hr_approved_by',
                'director_approved_at', 'director_approved_by',
                'accountant_approved_at', 'accountant_approved_by',
                'ceo_approved_at', 'ceo_approved_by',
            ]);
        });
    }
};
