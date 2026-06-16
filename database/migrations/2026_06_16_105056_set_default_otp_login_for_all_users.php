<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->update(['uses_password_login' => false]);
    }

    public function down(): void
    {
        DB::table('users')->update(['uses_password_login' => true]);
    }
};
