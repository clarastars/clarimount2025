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
            $table->string('attestation_type', 30)->default('none')->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('salary_certificate_requests', function (Blueprint $table) {
            $table->dropColumn('attestation_type');
        });
    }
};
