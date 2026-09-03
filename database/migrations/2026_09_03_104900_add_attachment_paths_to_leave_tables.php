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
        Schema::table('leave_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('leave_requests', 'attachment_paths')) {
                $table->json('attachment_paths')->nullable()->after('attachment_path');
            }
        });

        Schema::table('leaves', function (Blueprint $table): void {
            if (! Schema::hasColumn('leaves', 'attachment_paths')) {
                $table->json('attachment_paths')->nullable()->after('attachment_path');
            }
        });

        $this->backfill('leave_requests');
        $this->backfill('leaves');
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('leave_requests', 'attachment_paths')) {
                $table->dropColumn('attachment_paths');
            }
        });

        Schema::table('leaves', function (Blueprint $table): void {
            if (Schema::hasColumn('leaves', 'attachment_paths')) {
                $table->dropColumn('attachment_paths');
            }
        });
    }

    private function backfill(string $table): void
    {
        DB::table($table)
            ->whereNotNull('attachment_path')
            ->where(function ($query): void {
                $query->whereNull('attachment_paths')
                    ->orWhere('attachment_paths', '[]')
                    ->orWhere('attachment_paths', '');
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $path = (string) $row->attachment_path;
                    if ($path === '') {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([
                        'attachment_paths' => json_encode([$path], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }
};
