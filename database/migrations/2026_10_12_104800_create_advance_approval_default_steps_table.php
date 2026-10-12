<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_approval_default_steps', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedBigInteger('team_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('team_id', 'adv_default_step_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();

            $table->index(['is_active', 'sort_order'], 'adv_default_step_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_approval_default_steps');
    }
};
