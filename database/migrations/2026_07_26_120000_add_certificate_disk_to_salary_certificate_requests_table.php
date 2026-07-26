<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_certificate_requests', function (Blueprint $table) {
            $table->string('certificate_disk', 50)->nullable()->after('certificate_path');
            $table->string('certificate_name')->nullable()->after('certificate_disk');
        });
    }

    public function down(): void
    {
        Schema::table('salary_certificate_requests', function (Blueprint $table) {
            $table->dropColumn(['certificate_disk', 'certificate_name']);
        });
    }
};
