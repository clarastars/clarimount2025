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
            $table->decimal('attestation_fee', 10, 2)->nullable()->after('attestation_type');
        });

        Schema::table('employee_debts', function (Blueprint $table) {
            $table->unsignedBigInteger('salary_certificate_request_id')->nullable()->after('debt_type');
            $table->foreign('salary_certificate_request_id', 'ed_scr_fk')
                ->references('id')
                ->on('salary_certificate_requests')
                ->nullOnDelete();
            $table->unique('salary_certificate_request_id', 'ed_scr_unique');
        });
    }

    public function down(): void
    {
        Schema::table('employee_debts', function (Blueprint $table) {
            $table->dropForeign('ed_scr_fk');
            $table->dropUnique('ed_scr_unique');
            $table->dropColumn('salary_certificate_request_id');
        });

        Schema::table('salary_certificate_requests', function (Blueprint $table) {
            $table->dropColumn('attestation_fee');
        });
    }
};
