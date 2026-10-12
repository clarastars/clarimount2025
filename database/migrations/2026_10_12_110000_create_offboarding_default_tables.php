<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offboarding_default_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('attachment_mode', 20)->default('optional');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'off_def_tpl_active_order_idx');
        });

        Schema::create('offboarding_default_item_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('default_checklist_item_id');
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedBigInteger('team_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('default_checklist_item_id', 'off_def_item_step_tpl_fk')
                ->references('id')
                ->on('offboarding_default_checklist_items')
                ->cascadeOnDelete();
            $table->foreign('team_id', 'off_def_item_step_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();
            $table->index(['default_checklist_item_id', 'is_active', 'sort_order'], 'off_def_item_steps_active_idx');
        });

        Schema::create('offboarding_default_clearance_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedBigInteger('team_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('team_id', 'off_def_clr_step_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();
            $table->index(['is_active', 'sort_order'], 'off_def_clr_steps_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_default_clearance_approval_steps');
        Schema::dropIfExists('offboarding_default_item_approval_steps');
        Schema::dropIfExists('offboarding_default_checklist_items');
    }
};
