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
        Schema::create('advance_entitlement_tiers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('min_months');
            $table->unsignedInteger('max_months')->nullable();
            $table->decimal('max_amount', 12, 2);
            $table->unsignedInteger('max_installments');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'min_months']);
        });

        $now = now();

        DB::table('advance_entitlement_tiers')->insert([
            [
                'min_months' => 3,
                'max_months' => 11,
                'max_amount' => 300,
                'max_installments' => 3,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_months' => 12,
                'max_months' => 23,
                'max_amount' => 1000,
                'max_installments' => 6,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'min_months' => 24,
                'max_months' => null,
                'max_amount' => 5000,
                'max_installments' => 12,
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_entitlement_tiers');
    }
};
