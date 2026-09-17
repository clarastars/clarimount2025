<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveDaysUsedImport;
use App\Models\LeaveDaysUsedImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class LeaveDaysUsedImportService
{
    /**
     * @return array{
     *     import_id: int,
     *     updated: int,
     *     skipped: int,
     *     rows_processed: int,
     *     results: list<array{
     *         row: int,
     *         id_number: string,
     *         days_added: float|null,
     *         previous: float|null,
     *         new: float|null,
     *         employee_id: int|null,
     *         status: string,
     *         message: string
     *     }>
     * }
     */
    public function import(UploadedFile $file, ?User $user = null): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath() ?: $file->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($rows === []) {
            throw new \InvalidArgumentException(__('messages.settings.leave_days_used_import_empty_file'));
        }

        $headerRow = array_shift($rows);
        if (! is_array($headerRow)) {
            throw new \InvalidArgumentException(__('messages.settings.leave_days_used_import_invalid_headers'));
        }

        $columnMap = $this->resolveColumnMap($headerRow);
        if ($columnMap['id_number'] === null || $columnMap['days'] === null) {
            throw new \InvalidArgumentException(__('messages.settings.leave_days_used_import_invalid_headers'));
        }

        $results = [];
        $updated = 0;
        $skipped = 0;
        $rowsProcessed = 0;

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $excelRowNumber = $index + 2; // header is row 1
            $idRaw = $this->cellString($row[$columnMap['id_number']] ?? null);
            $daysRaw = $row[$columnMap['days']] ?? null;

            if ($idRaw === '' && $this->isBlank($daysRaw)) {
                continue;
            }

            $rowsProcessed++;

            if ($idRaw === '') {
                $skipped++;
                $results[] = $this->resultRow($excelRowNumber, '', null, null, null, null, 'skipped', __('messages.settings.leave_days_used_import_missing_id'));

                continue;
            }

            $days = $this->parseDays($daysRaw);
            if ($days === null) {
                $skipped++;
                $results[] = $this->resultRow($excelRowNumber, $idRaw, null, null, null, null, 'skipped', __('messages.settings.leave_days_used_import_invalid_days'));

                continue;
            }

            if ($days < 0) {
                $skipped++;
                $results[] = $this->resultRow($excelRowNumber, $idRaw, $days, null, null, null, 'skipped', __('messages.settings.leave_days_used_import_negative_days'));

                continue;
            }

            $matches = $this->findEmployeesByIdNumber($idRaw);

            if ($matches->isEmpty()) {
                $skipped++;
                $results[] = $this->resultRow($excelRowNumber, $idRaw, $days, null, null, null, 'skipped', __('messages.settings.leave_days_used_import_not_found'));

                continue;
            }

            if ($matches->count() > 1) {
                $skipped++;
                $results[] = $this->resultRow($excelRowNumber, $idRaw, $days, null, null, null, 'skipped', __('messages.settings.leave_days_used_import_ambiguous'));

                continue;
            }

            /** @var Employee $employee */
            $employee = $matches->first();

            $outcome = DB::transaction(function () use ($employee, $days): array {
                $locked = Employee::query()->lockForUpdate()->findOrFail($employee->id);
                $previous = round((float) ($locked->leave_days_used ?? 0), 2);
                $new = round($previous + $days, 2);
                $locked->update(['leave_days_used' => $new]);

                return [
                    'employee_id' => (int) $locked->id,
                    'previous' => $previous,
                    'new' => $new,
                ];
            });

            $updated++;
            $results[] = $this->resultRow(
                $excelRowNumber,
                $idRaw,
                $days,
                $outcome['previous'],
                $outcome['new'],
                $outcome['employee_id'],
                'updated',
                __('messages.settings.leave_days_used_import_updated'),
            );
        }

        $import = DB::transaction(function () use ($user, $file, $updated, $skipped, $rowsProcessed, $results): LeaveDaysUsedImport {
            $import = LeaveDaysUsedImport::query()->create([
                'user_id' => $user?->id,
                'original_filename' => $file->getClientOriginalName(),
                'updated_count' => $updated,
                'skipped_count' => $skipped,
                'rows_processed' => $rowsProcessed,
            ]);

            foreach ($results as $result) {
                LeaveDaysUsedImportRow::query()->create([
                    'leave_days_used_import_id' => $import->id,
                    'excel_row' => $result['row'],
                    'id_number' => $result['id_number'] !== '' ? $result['id_number'] : null,
                    'employee_id' => $result['employee_id'],
                    'days_added' => $result['days_added'],
                    'previous_value' => $result['previous'],
                    'new_value' => $result['new'],
                    'status' => $result['status'],
                    'message' => $result['message'],
                ]);
            }

            return $import;
        });

        return [
            'import_id' => (int) $import->id,
            'updated' => $updated,
            'skipped' => $skipped,
            'rows_processed' => $rowsProcessed,
            'results' => $results,
        ];
    }

    /**
     * @return array{
     *     import_id: int,
     *     restored: int,
     *     adjusted: int,
     *     failed: int,
     *     results: list<array{
     *         employee_id: int|null,
     *         id_number: string|null,
     *         days_removed: float|null,
     *         previous: float|null,
     *         restored_to: float|null,
     *         status: string,
     *         message: string
     *     }>
     * }
     */
    public function undo(LeaveDaysUsedImport $import, ?User $user = null): array
    {
        if ($import->isUndone()) {
            throw new RuntimeException(__('messages.settings.leave_days_used_import_already_undone'));
        }

        if ($import->updated_count <= 0) {
            throw new RuntimeException(__('messages.settings.leave_days_used_import_nothing_to_undo'));
        }

        return DB::transaction(function () use ($import, $user): array {
            $lockedImport = LeaveDaysUsedImport::query()
                ->lockForUpdate()
                ->findOrFail($import->id);

            if ($lockedImport->isUndone()) {
                throw new RuntimeException(__('messages.settings.leave_days_used_import_already_undone'));
            }

            $updatedRows = LeaveDaysUsedImportRow::query()
                ->where('leave_days_used_import_id', $lockedImport->id)
                ->where('status', 'updated')
                ->whereNotNull('employee_id')
                ->orderBy('id')
                ->get();

            $results = [];
            $restored = 0;
            $adjusted = 0;
            $failed = 0;

            foreach ($updatedRows as $row) {
                $employee = Employee::query()->lockForUpdate()->find($row->employee_id);

                if ($employee === null) {
                    $failed++;
                    $results[] = [
                        'employee_id' => $row->employee_id,
                        'id_number' => $row->id_number,
                        'days_removed' => null,
                        'previous' => null,
                        'restored_to' => null,
                        'status' => 'failed',
                        'message' => __('messages.settings.leave_days_used_import_undo_employee_missing'),
                    ];

                    continue;
                }

                $current = round((float) ($employee->leave_days_used ?? 0), 2);
                $daysAdded = round((float) ($row->days_added ?? 0), 2);
                $importedNew = round((float) ($row->new_value ?? 0), 2);
                $importedPrevious = round((float) ($row->previous_value ?? 0), 2);

                if (abs($current - $importedNew) < 0.001) {
                    $employee->update(['leave_days_used' => $importedPrevious]);
                    $restored++;
                    $results[] = [
                        'employee_id' => (int) $employee->id,
                        'id_number' => $row->id_number,
                        'days_removed' => $daysAdded,
                        'previous' => $current,
                        'restored_to' => $importedPrevious,
                        'status' => 'restored',
                        'message' => __('messages.settings.leave_days_used_import_undo_restored'),
                    ];

                    continue;
                }

                $restoredTo = max(0.0, round($current - $daysAdded, 2));
                $employee->update(['leave_days_used' => $restoredTo]);
                $adjusted++;
                $results[] = [
                    'employee_id' => (int) $employee->id,
                    'id_number' => $row->id_number,
                    'days_removed' => $daysAdded,
                    'previous' => $current,
                    'restored_to' => $restoredTo,
                    'status' => 'adjusted',
                    'message' => __('messages.settings.leave_days_used_import_undo_adjusted'),
                ];
            }

            $lockedImport->update([
                'undone_at' => now(),
                'undone_by' => $user?->id,
            ]);

            return [
                'import_id' => (int) $lockedImport->id,
                'restored' => $restored,
                'adjusted' => $adjusted,
                'failed' => $failed,
                'results' => $results,
            ];
        });
    }

    /**
     * @return list<array{
     *     id: int,
     *     original_filename: string|null,
     *     updated_count: int,
     *     skipped_count: int,
     *     rows_processed: int,
     *     can_undo: bool,
     *     undone_at: string|null,
     *     created_at: string|null
     * }>
     */
    public function recentImports(int $limit = 10): array
    {
        return LeaveDaysUsedImport::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (LeaveDaysUsedImport $import): array => [
                'id' => (int) $import->id,
                'original_filename' => $import->original_filename,
                'updated_count' => (int) $import->updated_count,
                'skipped_count' => (int) $import->skipped_count,
                'rows_processed' => (int) $import->rows_processed,
                'can_undo' => $import->canUndo(),
                'undone_at' => $import->undone_at?->timezone('Asia/Riyadh')->toDateTimeString(),
                'created_at' => $import->created_at?->timezone('Asia/Riyadh')->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Employee>
     */
    private function findEmployeesByIdNumber(string $idRaw): Collection
    {
        $candidates = [$idRaw];
        $withoutLeadingZeros = ltrim($idRaw, '0');
        if ($withoutLeadingZeros !== '' && $withoutLeadingZeros !== $idRaw) {
            $candidates[] = $withoutLeadingZeros;
        }

        $matches = Employee::query()
            ->whereIn('id_number', $candidates)
            ->get();

        $exact = $matches->filter(fn (Employee $e) => (string) $e->id_number === $idRaw)->values();

        return $exact->isNotEmpty() ? $exact : $matches;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array{id_number: int|null, days: int|null}
     */
    private function resolveColumnMap(array $headerRow): array
    {
        $idIndex = null;
        $daysIndex = null;

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);

            if ($idIndex === null && in_array($normalized, [
                'id_number',
                'idnumber',
                'national_id',
                'nationalid',
                'identity',
                'رقمالهوية',
                'الهوية',
            ], true)) {
                $idIndex = (int) $index;
            }

            if ($daysIndex === null && in_array($normalized, [
                'leave_days_used',
                'leavedaysused',
                'used_days',
                'useddays',
                'days',
                'days_used',
                'daysused',
                'أيامالإجازةالمستخدمة',
                'ايامالاجازةالمستخدمة',
                'اياماجازةمستخدمة',
                'المستخدمة',
            ], true)) {
                $daysIndex = (int) $index;
            }
        }

        return [
            'id_number' => $idIndex,
            'days' => $daysIndex,
        ];
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = mb_strtolower($header);
        $header = str_replace([' ', '-', '.', "\t", '_'], '', $header);

        return $header;
    }

    private function cellString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) || is_int($value)) {
            if (is_float($value) && floor($value) == $value) {
                return sprintf('%.0f', $value);
            }

            return trim((string) $value);
        }

        return trim((string) $value);
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    private function parseDays(mixed $value): ?float
    {
        if ($this->isBlank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        if (is_string($value)) {
            $cleaned = str_replace([',', ' '], ['', ''], trim($value));
            if (is_numeric($cleaned)) {
                return round((float) $cleaned, 2);
            }
        }

        return null;
    }

    /**
     * @return array{
     *     row: int,
     *     id_number: string,
     *     days_added: float|null,
     *     previous: float|null,
     *     new: float|null,
     *     employee_id: int|null,
     *     status: string,
     *     message: string
     * }
     */
    private function resultRow(
        int $row,
        string $idNumber,
        ?float $daysAdded,
        ?float $previous,
        ?float $new,
        ?int $employeeId,
        string $status,
        string $message,
    ): array {
        return [
            'row' => $row,
            'id_number' => $idNumber,
            'days_added' => $daysAdded,
            'previous' => $previous,
            'new' => $new,
            'employee_id' => $employeeId,
            'status' => $status,
            'message' => $message,
        ];
    }
}
