<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_UNIQUE = 'company_user_access_company_id_user_id_team_id_unique';
    private const SCOPED_UNIQUE = 'cua_company_user_team_department_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('company_user_access', 'department_id')) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->foreignUuid('department_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('departments')
                    ->nullOnDelete();
            });
        }

        if ($this->hasIndex('company_user_access', self::LEGACY_UNIQUE)) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->dropUnique(['company_id', 'user_id', 'team_id']);
            });
        }

        if (! $this->hasIndex('company_user_access', self::SCOPED_UNIQUE)) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->unique(['company_id', 'user_id', 'team_id', 'department_id'], self::SCOPED_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        $rows = DB::table('company_user_access')
            ->select('company_id', 'user_id', 'team_id')
            ->selectRaw('MIN(id) as keep_id')
            ->groupBy('company_id', 'user_id', 'team_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('company_user_access')
                ->where('company_id', $row->company_id)
                ->where('user_id', $row->user_id)
                ->where('team_id', $row->team_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        if ($this->hasIndex('company_user_access', self::SCOPED_UNIQUE)) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->dropUnique(self::SCOPED_UNIQUE);
            });
        }

        if (Schema::hasColumn('company_user_access', 'department_id')) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('department_id');
            });
        }

        if (! $this->hasIndex('company_user_access', self::LEGACY_UNIQUE)) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->unique(['company_id', 'user_id', 'team_id'], self::LEGACY_UNIQUE);
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
