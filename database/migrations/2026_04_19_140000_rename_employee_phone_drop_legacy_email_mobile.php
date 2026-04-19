<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename phone → personal_phone, fold mobile into work_phone, migrate legacy email into work_email, then drop email & mobile.
     */
    public function up(): void
    {
        if (Schema::hasColumn('employees', 'email')) {
            DB::table('employees')
                ->where(function ($q) {
                    $q->whereNull('work_email')->orWhere('work_email', '=', '');
                })
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->update(['work_email' => DB::raw('email')]);
        }

        if (Schema::hasColumn('employees', 'mobile') && Schema::hasColumn('employees', 'work_phone')) {
            DB::table('employees')
                ->where(function ($q) {
                    $q->whereNull('work_phone')->orWhere('work_phone', '=', '');
                })
                ->whereNotNull('mobile')
                ->where('mobile', '!=', '')
                ->update(['work_phone' => DB::raw('mobile')]);
        }

        if (Schema::hasColumn('employees', 'email')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique(['email']);
            });
        }

        Schema::table('employees', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('employees', 'email')) {
                $cols[] = 'email';
            }
            if (Schema::hasColumn('employees', 'mobile')) {
                $cols[] = 'mobile';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        if (Schema::hasColumn('employees', 'phone')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->renameColumn('phone', 'personal_phone');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('employees', 'personal_phone')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->renameColumn('personal_phone', 'phone');
            });
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('birth_date');
            $table->string('mobile')->nullable();
        });
    }
};
