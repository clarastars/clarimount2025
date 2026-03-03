<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('allowance_housing', 10, 2)->nullable()->after('allowances');
            $table->decimal('allowance_transportation', 10, 2)->nullable()->after('allowance_housing');
            $table->decimal('allowance_other', 10, 2)->nullable()->after('allowance_transportation');
            $table->decimal('allowance_food', 10, 2)->nullable()->after('allowance_other');
            $table->decimal('allowance_personal_car', 10, 2)->nullable()->after('allowance_food');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'allowance_housing',
                'allowance_transportation',
                'allowance_other',
                'allowance_food',
                'allowance_personal_car',
            ]);
        });
    }
};
