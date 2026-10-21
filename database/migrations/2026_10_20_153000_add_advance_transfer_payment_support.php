<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advance_requests', function (Blueprint $table): void {
            $table->string('payment_status', 20)->default('unpaid')->after('status');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
            $table->string('payment_receipt_path')->nullable()->after('paid_by');
            $table->text('payment_notes')->nullable()->after('payment_receipt_path');

            $table->foreign('paid_by', 'adv_req_paid_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['status', 'payment_status'], 'adv_req_status_payment_idx');
        });

        Schema::table('employee_debts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('installment_start_year')->nullable()->after('advance_disbursement_salary_run_id');
            $table->unsignedTinyInteger('installment_start_month')->nullable()->after('installment_start_year');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedBigInteger('advance_transfer_team_id')->nullable()->after('owner_id');
            $table->foreign('advance_transfer_team_id', 'companies_adv_transfer_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();
        });

        Schema::create('advance_transfer_defaults', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamps();

            $table->foreign('team_id', 'adv_transfer_default_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_transfer_defaults');

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropForeign('companies_adv_transfer_team_fk');
            $table->dropColumn('advance_transfer_team_id');
        });

        Schema::table('employee_debts', function (Blueprint $table): void {
            $table->dropColumn(['installment_start_year', 'installment_start_month']);
        });

        Schema::table('advance_requests', function (Blueprint $table): void {
            $table->dropForeign('adv_req_paid_by_fk');
            $table->dropIndex('adv_req_status_payment_idx');
            $table->dropColumn([
                'payment_status',
                'paid_at',
                'paid_by',
                'payment_receipt_path',
                'payment_notes',
            ]);
        });
    }
};
