<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->decimal('current_remaining_at_submit', 10, 2)->nullable()->after('days');
            $table->decimal('projected_remaining_at_start', 10, 2)->nullable()->after('current_remaining_at_submit');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn([
                'current_remaining_at_submit',
                'projected_remaining_at_start',
            ]);
        });
    }
};
