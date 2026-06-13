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
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('leave_accrued_balance', 10, 2)
                ->default(0)
                ->after('annual_leave_balance');
        });

        Schema::create('leave_accrual_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->char('accrual_period', 7)->comment('YYYY-MM');
            $table->decimal('days_accrued', 8, 2);
            $table->unsignedInteger('annual_entitlement');
            $table->decimal('balance_after', 10, 2);
            $table->timestamps();

            $table->unique(['employee_id', 'accrual_period']);
            $table->index('accrual_period');
        });

        $this->initializeExistingAccruedBalances();
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_accrual_logs');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('leave_accrued_balance');
        });
    }

    private function initializeExistingAccruedBalances(): void
    {
        DB::table('employees')
            ->orderBy('id')
            ->chunkById(200, function ($employees): void {
                foreach ($employees as $employee) {
                    $entitlement = (int) ($employee->annual_leave_balance ?? 21);

                    DB::table('employees')
                        ->where('id', $employee->id)
                        ->update([
                            'leave_accrued_balance' => $entitlement,
                        ]);
                }
            });
    }
};
