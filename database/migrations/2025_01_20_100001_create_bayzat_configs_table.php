<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bayzat_configs')) {
            return;
        }

        Schema::create('bayzat_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->text('api_key'); // Will be encrypted
            $table->string('api_url')->default('https://integration.bayzat.com/attendance');
            $table->boolean('is_enabled')->default(true);
            $table->enum('sync_frequency', ['manual', 'hourly', 'daily'])->default('manual');
            $table->timestamp('last_sync_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->unique('company_id', 'unique_company_config');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bayzat_configs');
    }
};
