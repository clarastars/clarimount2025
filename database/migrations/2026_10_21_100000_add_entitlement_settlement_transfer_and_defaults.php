<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_entitlement_settlements', function (Blueprint $table): void {
            $table->string('payment_status', 20)->default('unpaid')->after('status');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
            $table->string('payment_receipt_path')->nullable()->after('paid_by');
            $table->text('payment_notes')->nullable()->after('payment_receipt_path');

            $table->foreign('paid_by', 'ent_set_paid_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['status', 'payment_status'], 'ent_set_status_payment_idx');
        });

        DB::table('employee_entitlement_settlements')
            ->where('status', 'approved')
            ->update(['payment_status' => 'paid']);

        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedBigInteger('entitlement_settlement_transfer_team_id')->nullable()->after('advance_transfer_team_id');
            $table->foreign('entitlement_settlement_transfer_team_id', 'companies_ent_set_transfer_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();
        });

        Schema::create('entitlement_settlement_approval_default_steps', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedBigInteger('team_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('team_id', 'ent_set_default_step_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();

            $table->index(['is_active', 'sort_order'], 'ent_set_default_step_active_order_idx');
        });

        Schema::create('entitlement_settlement_transfer_defaults', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamps();

            $table->foreign('team_id', 'ent_set_transfer_default_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlement_settlement_transfer_defaults');
        Schema::dropIfExists('entitlement_settlement_approval_default_steps');

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropForeign('companies_ent_set_transfer_team_fk');
            $table->dropColumn('entitlement_settlement_transfer_team_id');
        });

        Schema::table('employee_entitlement_settlements', function (Blueprint $table): void {
            $table->dropForeign('ent_set_paid_by_fk');
            $table->dropIndex('ent_set_status_payment_idx');
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
