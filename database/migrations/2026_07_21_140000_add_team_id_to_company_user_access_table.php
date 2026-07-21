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
        if (! Schema::hasColumn('company_user_access', 'team_id')) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->foreignId('team_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('teams')
                    ->nullOnDelete();
            });
        }

        // MySQL may use the unique (company_id, user_id) index for the company_id FK.
        // Add a dedicated company_id index before dropping that unique key.
        $this->ensureCompanyIdIndex();

        if ($this->hasIndex('company_user_access', 'company_user_access_company_id_user_id_unique')) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->dropUnique(['company_id', 'user_id']);
            });
        }

        if (! $this->hasIndex('company_user_access', 'company_user_access_company_id_user_id_team_id_unique')) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->unique(['company_id', 'user_id', 'team_id']);
            });
        }

        $this->migrateLegacyAccessToTeams();
    }

    public function down(): void
    {
        $rows = DB::table('company_user_access')
            ->select('company_id', 'user_id')
            ->selectRaw('MIN(id) as keep_id')
            ->groupBy('company_id', 'user_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('company_user_access')
                ->where('company_id', $row->company_id)
                ->where('user_id', $row->user_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }

        if ($this->hasIndex('company_user_access', 'company_user_access_company_id_user_id_team_id_unique')) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->dropUnique(['company_id', 'user_id', 'team_id']);
            });
        }

        if (Schema::hasColumn('company_user_access', 'team_id')) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('team_id');
            });
        }

        if (! $this->hasIndex('company_user_access', 'company_user_access_company_id_user_id_unique')) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->unique(['company_id', 'user_id']);
            });
        }

        if ($this->hasIndex('company_user_access', 'company_user_access_company_id_index')) {
            Schema::table('company_user_access', function (Blueprint $table): void {
                $table->dropIndex(['company_id']);
            });
        }
    }

    /**
     * Preserve existing access: copy each legacy (user, company) row onto every team
     * the user already belongs to, then remove the unscoped legacy rows.
     */
    private function migrateLegacyAccessToTeams(): void
    {
        $teamsKey = (string) config('permission.column_names.team_foreign_key', 'team_id');
        $pivotTable = (string) config('permission.table_names.model_has_roles');
        $rolesTable = (string) config('permission.table_names.roles');
        $teamRoleNames = ['team-member', 'team-admin', 'team-viewer'];

        $legacyRows = DB::table('company_user_access')
            ->whereNull('team_id')
            ->get(['id', 'company_id', 'user_id', 'created_at', 'updated_at']);

        if ($legacyRows->isEmpty()) {
            return;
        }

        $userIds = $legacyRows->pluck('user_id')->unique()->values();

        $teamsByUser = DB::table($pivotTable)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.role_id")
            ->where("{$pivotTable}.model_type", 'App\\Models\\User')
            ->whereIn("{$pivotTable}.model_id", $userIds->all())
            ->whereIn("{$rolesTable}.name", $teamRoleNames)
            ->whereNotNull("{$pivotTable}.{$teamsKey}")
            ->get([
                "{$pivotTable}.model_id as user_id",
                "{$pivotTable}.{$teamsKey} as team_id",
            ])
            ->groupBy(fn ($row) => (int) $row->user_id)
            ->map(fn ($rows) => $rows->pluck('team_id')->map(fn ($id) => (int) $id)->unique()->values());

        $primaryTeams = DB::table('users')
            ->whereIn('id', $userIds->all())
            ->whereNotNull('team_id')
            ->get(['id', 'team_id'])
            ->mapWithKeys(fn ($row) => [(int) $row->id => (int) $row->team_id]);

        $now = now();
        $inserts = [];
        $legacyIdsToDelete = [];

        foreach ($legacyRows as $row) {
            $userId = (int) $row->user_id;
            $teamIds = collect($teamsByUser->get($userId, []))
                ->when(
                    isset($primaryTeams[$userId]),
                    fn ($ids) => $ids->push((int) $primaryTeams[$userId])
                )
                ->unique()
                ->values();

            if ($teamIds->isEmpty()) {
                // Keep unscoped row so access is not lost for users without team roles yet.
                continue;
            }

            foreach ($teamIds as $teamId) {
                $inserts[] = [
                    'company_id' => (int) $row->company_id,
                    'user_id' => $userId,
                    'team_id' => $teamId,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ];
            }

            $legacyIdsToDelete[] = (int) $row->id;
        }

        if ($inserts !== []) {
            foreach (array_chunk($inserts, 500) as $chunk) {
                DB::table('company_user_access')->insertOrIgnore($chunk);
            }
        }

        if ($legacyIdsToDelete !== []) {
            DB::table('company_user_access')->whereIn('id', $legacyIdsToDelete)->delete();
        }
    }

    private function ensureCompanyIdIndex(): void
    {
        if ($this->hasIndex('company_user_access', 'company_user_access_company_id_index')) {
            return;
        }

        // Prefer a non-unique index name Laravel would generate for index(['company_id']).
        Schema::table('company_user_access', function (Blueprint $table): void {
            $table->index('company_id');
        });
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
