<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\Company;
use App\Models\Country;
use App\Models\Nationality;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class EmployeeImportService
{
    public const IMPORT_MODE_CREATE = 'create';

    public const IMPORT_MODE_UPDATE = 'update';

    private const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * CSV column names we read during import (used to normalize sparse rows in update mode).
     *
     * @var list<string>
     */
    private const CSV_IMPORT_COLUMNS = [
        'id',
        'employee_id',
        'first_name',
        'last_name',
        'father_name',
        'nationality',
        'residence_country',
        'birth_date',
        'personal_email',
        'work_email',
        'personal_phone',
        'work_phone',
        'email',
        'phone',
        'mobile',
        'fingerprint_device_id',
        'work_address',
        'department',
        'job_title',
        'shift_id',
        'basic_salary',
        'allowances',
        'allowance_housing',
        'allowance_transportation',
        'allowance_other',
        'allowance_food',
        'allowance_personal_car',
        'manager',
        'direct_manager',
        'additional_approver_2',
        'additional_approver_3',
        'hire_date',
        'employment_date',
        'probation_end_date',
        'employment_status',
        'termination_date',
        'departure_date',
        'departure_reason',
        'id_number',
        'residence_expiry_date',
        'contract_end_date',
        'exit_reentry_visa_expiry',
        'passport_number',
        'passport_expiry_date',
        'insurance_policy',
        'insurance_expiry_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_email',
        'emergency_contact_address',
        'notes',
    ];

    /**
     * Date columns stored on employees — normalized again on import execute (JSON round-trip / Excel quirks).
     *
     * @var list<string>
     */
    private const EMPLOYEE_IMPORT_DATE_FIELDS = [
        'birth_date',
        'hire_date',
        'employment_date',
        'probation_end_date',
        'termination_date',
        'departure_date',
        'residence_expiry_date',
        'contract_end_date',
        'exit_reentry_visa_expiry',
        'passport_expiry_date',
        'insurance_expiry_date',
    ];

    /**
     * Prefix CSV with UTF-8 BOM so Microsoft Excel detects UTF-8 (fixes Arabic mojibake).
     */
    private function csvForExcelDownload(string $csv): string
    {
        return self::UTF8_BOM . $csv;
    }

    /**
     * Generate a sample CSV file for employee import.
     */
    public function generateSampleCsv(Company $company): string
    {
        $headers = [
            'id',
            'employee_id',
            'first_name',
            'last_name',
            'father_name',
            'nationality',
            'residence_country',
            'birth_date',
            'personal_email',
            'work_email',
            'personal_phone',
            'work_phone',
            'fingerprint_device_id',
            'work_address',
            'department',
            'job_title',
            'shift_id',
            'basic_salary',
            'allowances',
            'allowance_housing',
            'allowance_transportation',
            'allowance_other',
            'allowance_food',
            'allowance_personal_car',
            'manager',
            'direct_manager',
            'additional_approver_2',
            'additional_approver_3',
            'hire_date',
            'employment_date',
            'probation_end_date',
            'employment_status',
            'termination_date',
            'departure_date',
            'departure_reason',
            'id_number',
            'residence_expiry_date',
            'contract_end_date',
            'exit_reentry_visa_expiry',
            'passport_number',
            'passport_expiry_date',
            'insurance_policy',
            'insurance_expiry_date',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_email',
            'emergency_contact_address',
            'notes',
        ];

        $sampleData = [
            '',
            'EMP001',
            'John',
            'Doe',
            'Michael',
            'American',
            'Saudi Arabia',
            '1990-01-15',
            'john.personal@example.com',
            'john.work@company.com',
            '+1234567890',
            '+1234567891',
            'FP001',
            '123 Main St, City',
            'IT Department',
            'Software Engineer',
            '1',
            '5000.00',
            '1000.00',
            '500.00',
            '200.00',
            '0.00',
            '200.00',
            '100.00',
            'Jane Smith',
            'Bob Johnson',
            'Alice Brown',
            'Charlie Wilson',
            '2023-01-01',
            '2023-01-01',
            '2023-07-01',
            'active',
            '',
            '',
            'Performance',
            'ID12345',
            '2025-12-31',
            '2024-12-31',
            '2024-06-30',
            'P123456',
            '2030-05-15',
            'INS789',
            '2024-12-31',
            'Emergency Contact',
            '+9876543210',
            'emergency@example.com',
            '456 Emergency St, City',
            'Sample employee record',
        ];

        $csv = implode(',', $headers) . "\n";
        $csv .= implode(',', array_map(function($field) {
            return '"' . str_replace('"', '""', $field) . '"';
        }, $sampleData)) . "\n";

        return $this->csvForExcelDownload($csv);
    }

    /**
     * Export existing employees to CSV.
     */
    public function exportEmployeesToCsv(Collection $employees): string
    {
        $headers = [
            'id',
            'employee_id',
            'first_name',
            'last_name',
            'father_name',
            'nationality',
            'residence_country',
            'birth_date',
            'personal_email',
            'work_email',
            'personal_phone',
            'work_phone',
            'fingerprint_device_id',
            'work_address',
            'department',
            'job_title',
            'shift_id',
            'basic_salary',
            'allowances',
            'allowance_housing',
            'allowance_transportation',
            'allowance_other',
            'allowance_food',
            'allowance_personal_car',
            'manager',
            'direct_manager',
            'additional_approver_2',
            'additional_approver_3',
            'hire_date',
            'employment_date',
            'probation_end_date',
            'employment_status',
            'termination_date',
            'departure_date',
            'departure_reason',
            'id_number',
            'residence_expiry_date',
            'contract_end_date',
            'exit_reentry_visa_expiry',
            'passport_number',
            'passport_expiry_date',
            'insurance_policy',
            'insurance_expiry_date',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_email',
            'emergency_contact_address',
            'notes',
        ];

        $csv = implode(',', $headers) . "\n";

        foreach ($employees as $employee) {
            $row = [
                $employee->id,
                $employee->employee_id,
                $employee->first_name,
                $employee->last_name,
                $employee->father_name,
                $employee->nationality ? $employee->nationality->name : '',
                $employee->residenceCountry ? $employee->residenceCountry->name : '',
                $employee->birth_date ? $employee->birth_date->format('Y-m-d') : '',
                $employee->personal_email,
                $employee->work_email,
                $employee->personal_phone,
                $employee->work_phone,
                $employee->fingerprint_device_id,
                $employee->work_address,
                $employee->department,
                $employee->job_title,
                $employee->shift_id,
                $employee->basic_salary ?? '',
                $employee->allowances ?? '',
                $employee->allowance_housing ?? '',
                $employee->allowance_transportation ?? '',
                $employee->allowance_other ?? '',
                $employee->allowance_food ?? '',
                $employee->allowance_personal_car ?? '',
                $employee->manager,
                $employee->direct_manager,
                $employee->additional_approver_2,
                $employee->additional_approver_3,
                $employee->hire_date ? $employee->hire_date->format('Y-m-d') : '',
                $employee->employment_date ? $employee->employment_date->format('Y-m-d') : '',
                $employee->probation_end_date ? $employee->probation_end_date->format('Y-m-d') : '',
                $employee->employment_status,
                $employee->termination_date ? $employee->termination_date->format('Y-m-d') : '',
                $employee->departure_date ? $employee->departure_date->format('Y-m-d') : '',
                $employee->departure_reason,
                $employee->id_number,
                $employee->residence_expiry_date ? $employee->residence_expiry_date->format('Y-m-d') : '',
                $employee->contract_end_date ? $employee->contract_end_date->format('Y-m-d') : '',
                $employee->exit_reentry_visa_expiry ? $employee->exit_reentry_visa_expiry->format('Y-m-d') : '',
                $employee->passport_number,
                $employee->passport_expiry_date ? $employee->passport_expiry_date->format('Y-m-d') : '',
                $employee->insurance_policy,
                $employee->insurance_expiry_date ? $employee->insurance_expiry_date->format('Y-m-d') : '',
                $employee->emergency_contact_name,
                $employee->emergency_contact_phone,
                $employee->emergency_contact_email,
                $employee->emergency_contact_address,
                $employee->notes,
            ];

            $csv .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', (string)$field) . '"';
            }, $row)) . "\n";
        }

        return $this->csvForExcelDownload($csv);
    }

    /**
     * Ensure every known CSV column exists on the row (sparse files in update mode).
     *
     * @param  array<string, string|null>  $rowData
     * @return array<string, string>
     */
    protected function normalizeRowDataKeys(array $rowData): array
    {
        $normalized = [];
        foreach (self::CSV_IMPORT_COLUMNS as $column) {
            $normalized[$column] = isset($rowData[$column]) ? $this->cleanCsvCell((string) $rowData[$column]) : '';
        }

        return $normalized;
    }

    /**
     * For update imports: fill blank CSV cells from the existing employee (partial update).
     */
    protected function fillRowDataBlanksFromEmployee(array $rowData, Employee $employee): array
    {
        $rowData = $this->normalizeRowDataKeys($rowData);

        $setIfBlank = function (string $key, ?string $value) use (&$rowData): void {
            if ($rowData[$key] !== '') {
                return;
            }
            if ($value === null || $value === '') {
                return;
            }
            $rowData[$key] = $value;
        };

        $date = static function ($d): ?string {
            if ($d === null) {
                return null;
            }
            if ($d instanceof \DateTimeInterface) {
                return $d->format('Y-m-d');
            }

            return null;
        };

        $setIfBlank('employee_id', $employee->employee_id !== null ? (string) $employee->employee_id : null);
        $setIfBlank('first_name', $employee->first_name);
        $setIfBlank('last_name', $employee->last_name);
        $setIfBlank('father_name', $employee->father_name);
        $setIfBlank('nationality', $employee->nationality !== null ? (string) $employee->nationality->name_en : null);
        $setIfBlank('residence_country', $employee->residenceCountry !== null ? (string) $employee->residenceCountry->name_en : null);
        $setIfBlank('birth_date', $date($employee->birth_date));
        $setIfBlank('personal_email', $employee->personal_email);
        $setIfBlank('work_email', $employee->work_email);
        $setIfBlank('personal_phone', $employee->personal_phone);
        $setIfBlank('work_phone', $employee->work_phone);
        $setIfBlank('fingerprint_device_id', $employee->fingerprint_device_id);
        $setIfBlank('work_address', $employee->work_address);

        $departmentLabel = null;
        if ($employee->relationLoaded('department') && $employee->getRelation('department')) {
            $departmentLabel = (string) $employee->getRelation('department')->name;
        } else {
            $departmentLabel = $employee->getRawOriginal('department');
        }
        $setIfBlank('department', $departmentLabel !== null && $departmentLabel !== '' ? (string) $departmentLabel : null);

        $setIfBlank('job_title', $employee->job_title);
        $setIfBlank('shift_id', $employee->shift_id !== null ? (string) $employee->shift_id : null);

        $setIfBlank('basic_salary', $employee->basic_salary !== null ? (string) $employee->basic_salary : null);
        $setIfBlank('allowances', $employee->allowances !== null ? (string) $employee->allowances : null);
        $setIfBlank('allowance_housing', $employee->allowance_housing !== null ? (string) $employee->allowance_housing : null);
        $setIfBlank('allowance_transportation', $employee->allowance_transportation !== null ? (string) $employee->allowance_transportation : null);
        $setIfBlank('allowance_other', $employee->allowance_other !== null ? (string) $employee->allowance_other : null);
        $setIfBlank('allowance_food', $employee->allowance_food !== null ? (string) $employee->allowance_food : null);
        $setIfBlank('allowance_personal_car', $employee->allowance_personal_car !== null ? (string) $employee->allowance_personal_car : null);

        $setIfBlank('manager', $employee->manager);
        $setIfBlank('direct_manager', $employee->direct_manager);
        $setIfBlank('additional_approver_2', $employee->additional_approver_2);
        $setIfBlank('additional_approver_3', $employee->additional_approver_3);

        $setIfBlank('hire_date', $date($employee->hire_date));
        $setIfBlank('employment_date', $date($employee->employment_date));
        $setIfBlank('probation_end_date', $date($employee->probation_end_date));
        $setIfBlank('employment_status', $employee->employment_status);
        $setIfBlank('termination_date', $date($employee->termination_date));
        $setIfBlank('departure_date', $date($employee->departure_date));
        $setIfBlank('departure_reason', $employee->departure_reason);
        $setIfBlank('id_number', $employee->id_number);
        $setIfBlank('residence_expiry_date', $date($employee->residence_expiry_date));
        $setIfBlank('contract_end_date', $date($employee->contract_end_date));
        $setIfBlank('exit_reentry_visa_expiry', $date($employee->exit_reentry_visa_expiry));
        $setIfBlank('passport_number', $employee->passport_number);
        $setIfBlank('passport_expiry_date', $date($employee->passport_expiry_date));
        $setIfBlank('insurance_policy', $employee->insurance_policy);
        $setIfBlank('insurance_expiry_date', $date($employee->insurance_expiry_date));
        $setIfBlank('emergency_contact_name', $employee->emergency_contact_name);
        $setIfBlank('emergency_contact_phone', $employee->emergency_contact_phone);
        $setIfBlank('emergency_contact_email', $employee->emergency_contact_email);
        $setIfBlank('emergency_contact_address', $employee->emergency_contact_address);
        $setIfBlank('notes', $employee->notes);

        return $rowData;
    }

    /**
     * Validate CSV file and return structured data.
     */
    public function validateCsv(string $filePath, Company $company, string $importMode = self::IMPORT_MODE_CREATE): array
    {
        try {
            $importMode = $importMode === self::IMPORT_MODE_UPDATE ? self::IMPORT_MODE_UPDATE : self::IMPORT_MODE_CREATE;

            $csvData = $this->parseCsvFile($filePath);

            if (empty($csvData)) {
                return [
                    'success' => false,
                    'errors' => ['The CSV file is empty or could not be parsed.'],
                ];
            }

            $rawHeaders = array_shift($csvData);
            if (!is_array($rawHeaders)) {
                return [
                    'success' => false,
                    'errors' => ['The CSV file has invalid headers.'],
                ];
            }

            $headers = array_map(
                fn ($h) => strtolower($this->cleanCsvCell((string) $h)),
                $rawHeaders
            );

            if ($importMode === self::IMPORT_MODE_UPDATE && ! in_array('id', $headers, true)) {
                return [
                    'success' => false,
                    'errors' => ['Missing required header: id (required for update imports).'],
                ];
            }

            // New-employee imports need name columns at file level. If the sheet includes "id",
            // rows are treated as updates by id (partial) and names come from DB when omitted.
            if ($importMode === self::IMPORT_MODE_CREATE && ! in_array('id', $headers, true)) {
                $requiredHeaders = ['first_name', 'last_name'];
                $missingHeaders = array_diff($requiredHeaders, $headers);
                if (! empty($missingHeaders)) {
                    return [
                        'success' => false,
                        'errors' => ['Missing required headers: ' . implode(', ', $missingHeaders)],
                    ];
                }
            }

            $validatedData = [];
            $errors = [];
            $rowNumber = 2;

            $countries = Country::get()->flatMap(function ($country) {
                return [
                    mb_strtolower($country->name_en) => $country->id,
                    mb_strtolower($country->name_ar) => $country->id,
                ];
            })->toArray();
            $nationalities = Nationality::get()->flatMap(function ($nationality) {
                return [
                    mb_strtolower($nationality->name_en) => $nationality->id,
                    mb_strtolower($nationality->name_ar) => $nationality->id,
                ];
            })->toArray();
            $departments = Department::where('company_id', $company->id)->pluck('name', 'id')->toArray();
            $shiftIds = Shift::pluck('id')->toArray();

            $idColumnIndex = array_search('id', $headers, true);
            $preloadedEmployees = collect();
            if ($importMode === self::IMPORT_MODE_UPDATE && $idColumnIndex !== false) {
                $ids = [];
                foreach ($csvData as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $idVal = isset($row[$idColumnIndex]) ? trim((string) $row[$idColumnIndex]) : '';
                    if ($idVal !== '' && is_numeric($idVal)) {
                        $ids[] = (int) $idVal;
                    }
                }
                $ids = array_unique($ids);
                if (! empty($ids)) {
                    $preloadedEmployees = Employee::query()
                        ->where('company_id', $company->id)
                        ->whereIn('id', $ids)
                        ->with(['nationality', 'residenceCountry', 'department'])
                        ->get()
                        ->keyBy('id');
                }
            }

            foreach ($csvData as $row) {
                if (! is_array($row)) {
                    $errors[] = "Row {$rowNumber}: Invalid row.";
                    $rowNumber++;

                    continue;
                }

                $row = array_pad(array_slice($row, 0, count($headers)), count($headers), '');
                $rowData = array_combine($headers, $row);
                if ($rowData === false) {
                    $errors[] = "Row {$rowNumber}: Row could not be parsed.";
                    $rowNumber++;

                    continue;
                }

                foreach ($rowData as $k => $v) {
                    $rowData[$k] = $this->cleanCsvCell((string) $v);
                }

                $validationResult = $this->validateRow(
                    $rowData,
                    $rowNumber,
                    $company,
                    $countries,
                    $nationalities,
                    $departments,
                    $shiftIds,
                    $importMode,
                    $preloadedEmployees
                );

                if (! empty($validationResult['errors'])) {
                    $errors = array_merge($errors, $validationResult['errors']);
                } else {
                    $validatedData[] = $validationResult['data'];
                }

                $rowNumber++;
            }

            if (! empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors,
                ];
            }

            $summary = $this->generateSummary($validatedData, $importMode);

            return [
                'success' => true,
                'data' => $validatedData,
                'summary' => $summary,
            ];
        } catch (\Exception $e) {
            Log::error('CSV validation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'errors' => ['Failed to validate CSV: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Parse CSV: auto delimiter (comma vs semicolon vs tab), UTF-16 from Excel, unlimited line length.
     */
    protected function parseCsvFile(string $filePath): array
    {
        $fullPath = Storage::disk('local')->path($filePath);
        $bytes = @file_get_contents($fullPath);
        if ($bytes === false || $bytes === '') {
            return [];
        }

        $tempPath = null;
        $normalized = $bytes;

        if (str_starts_with($normalized, "\xFF\xFE")) {
            $normalized = mb_convert_encoding($normalized, 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($normalized, "\xFE\xFF")) {
            $normalized = mb_convert_encoding($normalized, 'UTF-8', 'UTF-16BE');
        }

        if (str_starts_with($normalized, self::UTF8_BOM)) {
            $normalized = substr($normalized, strlen(self::UTF8_BOM));
        }

        $parsePath = $fullPath;
        if ($normalized !== $bytes) {
            $tempPath = tempnam(sys_get_temp_dir(), 'hr_emp_csv_');
            if ($tempPath === false) {
                Log::error('Could not create temp file for employee CSV import.');

                return [];
            }
            file_put_contents($tempPath, $normalized);
            $parsePath = $tempPath;
        }

        $handle = fopen($parsePath, 'r');
        if ($handle === false) {
            if ($tempPath !== null) {
                @unlink($tempPath);
            }

            return [];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            if ($tempPath !== null) {
                @unlink($tempPath);
            }

            return [];
        }

        $delimiter = $this->detectCsvDelimiter($firstLine);
        rewind($handle);

        $csvData = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $csvData[] = $row;
        }
        fclose($handle);

        if ($tempPath !== null) {
            @unlink($tempPath);
        }

        if (! empty($csvData[0][0]) && str_starts_with($csvData[0][0], self::UTF8_BOM)) {
            $csvData[0][0] = substr($csvData[0][0], strlen(self::UTF8_BOM));
        }

        return $csvData;
    }

    /**
     * Prefer the delimiter that splits the header row into the most columns (Excel "CSV UTF-8" often uses ';').
     */
    protected function detectCsvDelimiter(string $firstLine): string
    {
        $line = trim($firstLine);
        if ($line === '') {
            return ',';
        }
        if (str_starts_with($line, self::UTF8_BOM)) {
            $line = substr($line, strlen(self::UTF8_BOM));
        }

        $scores = [
            ',' => count(str_getcsv($line, ',')),
            ';' => count(str_getcsv($line, ';')),
            "\t" => count(str_getcsv($line, "\t")),
        ];

        arsort($scores);
        $best = (int) reset($scores);

        return $best >= 2 ? (string) array_key_first($scores) : ',';
    }

    /**
     * Trim spaces, UTF-8 BOM, and Unicode space characters (e.g. Excel NBSP) from a CSV cell.
     */
    protected function cleanCsvCell(string $value): string
    {
        $v = trim($value);
        if (str_starts_with($v, self::UTF8_BOM)) {
            $v = trim(substr($v, strlen(self::UTF8_BOM)));
        }
        $v = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $v) ?? $v;

        return trim($v);
    }

    /**
     * Map legacy CSV columns (email, phone, mobile) into work_email, personal_phone, and work_phone.
     *
     * @param  array<string, mixed>  $rowData
     * @return array<string, mixed>
     */
    protected function applyLegacyCsvAliases(array $rowData): array
    {
        $legacyEmail = trim((string) ($rowData['email'] ?? ''));
        if (trim((string) ($rowData['work_email'] ?? '')) === '' && $legacyEmail !== '') {
            $rowData['work_email'] = $legacyEmail;
        }

        $legacyPhone = trim((string) ($rowData['phone'] ?? ''));
        if (trim((string) ($rowData['personal_phone'] ?? '')) === '' && $legacyPhone !== '') {
            $rowData['personal_phone'] = $legacyPhone;
        }

        $legacyMobile = trim((string) ($rowData['mobile'] ?? ''));
        if (trim((string) ($rowData['work_phone'] ?? '')) === '' && $legacyMobile !== '') {
            $rowData['work_phone'] = $legacyMobile;
        }

        return $rowData;
    }

    /**
     * Validate a single row of CSV data.
     *
     * @param  array<string, mixed>  $rowData
     */
    protected function validateRow(
        array $rowData,
        int $rowNumber,
        Company $company,
        array $countries,
        array $nationalities,
        array $departments,
        array $shiftIds,
        string $importMode = self::IMPORT_MODE_CREATE,
        ?Collection $preloadedEmployees = null
    ): array {
        $preloadedEmployees = $preloadedEmployees ?? collect();

        $errors = [];
        $data = [];
        $existingEmployee = null;
        $isUpdate = false;

        if ($importMode === self::IMPORT_MODE_UPDATE) {
            $idRaw = trim((string) ($rowData['id'] ?? ''));
            if ($idRaw === '' || ! ctype_digit($idRaw)) {
                return ['errors' => ["Row {$rowNumber}: id is required for update imports."], 'data' => null];
            }

            $existingEmployee = $preloadedEmployees->get((int) $idRaw);
            if (! $existingEmployee) {
                return ['errors' => ["Row {$rowNumber}: Employee with ID {$idRaw} not found for this company."], 'data' => null];
            }

            $isUpdate = true;
        } else {
            $isUpdate = ! empty($rowData['id']) && is_numeric($rowData['id']);
            if ($isUpdate) {
                $existingEmployee = Employee::query()
                    ->with(['nationality', 'residenceCountry', 'department'])
                    ->where('id', (int) $rowData['id'])
                    ->where('company_id', $company->id)
                    ->first();

                if (! $existingEmployee) {
                    return ['errors' => ["Row {$rowNumber}: Employee with ID {$rowData['id']} not found."], 'data' => null];
                }
            }
        }

        // Sparse CSV / Excel: columns not present must keep existing DB values (same as blank cells).
        if ($isUpdate && $existingEmployee !== null) {
            $rowData = $this->fillRowDataBlanksFromEmployee($rowData, $existingEmployee);
        }

        $rowData = $this->applyLegacyCsvAliases($rowData);

        $requiredFields = ['first_name', 'last_name'];
        foreach ($requiredFields as $field) {
            if (empty(trim((string) ($rowData[$field] ?? '')))) {
                $errors[] = "Row {$rowNumber}: {$field} is required.";
            }
        }

        $workEmail = trim((string) ($rowData['work_email'] ?? ''));
        $personalEmail = trim((string) ($rowData['personal_email'] ?? ''));
        if ($workEmail === '' && $personalEmail === '') {
            $errors[] = "Row {$rowNumber}: Either work_email or personal_email is required.";
        }

        $excludeId = $isUpdate ? $existingEmployee?->id : null;
        foreach (['work_email' => $workEmail, 'personal_email' => $personalEmail] as $column => $addr) {
            if ($addr === '') {
                continue;
            }
            if (! filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNumber}: Invalid email format for {$column}.";

                continue;
            }
            $dup = Employee::query()
                ->where('company_id', $company->id)
                ->where($column, $addr)
                ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists();
            if ($dup) {
                $errors[] = "Row {$rowNumber}: {$column} already exists for another employee.";
            }
        }

        $personalPhone = trim((string) ($rowData['personal_phone'] ?? ''));
        $workPhone = trim((string) ($rowData['work_phone'] ?? ''));
        if ($personalPhone === '' && $workPhone === '') {
            $errors[] = "Row {$rowNumber}: Either personal_phone or work_phone is required.";
        }

        if (! empty($rowData['employment_status']) && ! in_array($rowData['employment_status'], ['active', 'inactive', 'terminated'], true)) {
            $errors[] = "Row {$rowNumber}: Invalid employment status. Must be: active, inactive, or terminated.";
        }

        foreach (self::EMPLOYEE_IMPORT_DATE_FIELDS as $field) {
            $rawDate = isset($rowData[$field]) ? trim((string) $rowData[$field]) : '';
            if ($rawDate !== '') {
                $parsed = $this->parseImportDateString($rawDate);
                if ($parsed === null) {
                    $errors[] = "Row {$rowNumber}: Invalid date format for {$field}.";
                } else {
                    $rowData[$field] = $parsed->format('Y-m-d');
                }
            }
        }

        if (! empty($rowData['nationality'])) {
            $nationalityId = $this->findNationalityId((string) $rowData['nationality'], $nationalities);
            if (! $nationalityId) {
                $errors[] = "Row {$rowNumber}: Nationality '{$rowData['nationality']}' not found.";
            } else {
                $data['nationality_id'] = $nationalityId;
            }
        }

        if (! empty($rowData['residence_country'])) {
            $countryId = $this->findCountryId((string) $rowData['residence_country'], $countries);
            if (! $countryId) {
                $errors[] = "Row {$rowNumber}: Residence country '{$rowData['residence_country']}' not found.";
            } else {
                $data['residence_country_id'] = $countryId;
            }
        }

        if (! empty($rowData['department'])) {
            $departmentId = $this->findDepartmentId((string) $rowData['department'], $departments);
            if (! $departmentId) {
                $errors[] = "Row {$rowNumber}: Department '{$rowData['department']}' not found.";
            } else {
                $data['department_id'] = $departmentId;
            }
        }

        if (isset($rowData['shift_id']) && $rowData['shift_id'] !== '' && $rowData['shift_id'] !== null) {
            $shiftId = is_numeric($rowData['shift_id']) ? (int) $rowData['shift_id'] : null;
            if ($shiftId === null || ! in_array($shiftId, $shiftIds, true)) {
                $errors[] = "Row {$rowNumber}: Invalid or unknown shift_id.";
            } else {
                $data['shift_id'] = $shiftId;
            }
        }

        $numericFields = [
            'basic_salary', 'allowances', 'allowance_housing', 'allowance_transportation',
            'allowance_other', 'allowance_food', 'allowance_personal_car',
        ];
        foreach ($numericFields as $field) {
            if (isset($rowData[$field]) && $rowData[$field] !== '' && $rowData[$field] !== null) {
                if (! is_numeric($rowData[$field])) {
                    $errors[] = "Row {$rowNumber}: {$field} must be a number.";
                }
            }
        }

        if (! empty($errors)) {
            return ['errors' => $errors, 'data' => null];
        }

        $basicSalary = isset($rowData['basic_salary']) && $rowData['basic_salary'] !== '' && is_numeric($rowData['basic_salary'])
            ? (float) $rowData['basic_salary'] : 0.0;
        $allowances = isset($rowData['allowances']) && $rowData['allowances'] !== '' && is_numeric($rowData['allowances'])
            ? (float) $rowData['allowances'] : 0.0;

        $data = array_merge($data, [
            'id' => $isUpdate ? $existingEmployee->id : null,
            'company_id' => $company->id,
            'employee_id' => $rowData['employee_id'] ?? null,
            'first_name' => $rowData['first_name'],
            'last_name' => $rowData['last_name'],
            'father_name' => $rowData['father_name'] ?? null,
            'birth_date' => (isset($rowData['birth_date']) && trim((string) $rowData['birth_date']) !== '') ? $rowData['birth_date'] : null,
            'personal_email' => $personalEmail !== '' ? $personalEmail : null,
            'work_email' => $workEmail !== '' ? $workEmail : null,
            'personal_phone' => $personalPhone !== '' ? $personalPhone : null,
            'work_phone' => $workPhone !== '' ? $workPhone : null,
            'fingerprint_device_id' => $rowData['fingerprint_device_id'] ?? null,
            'work_address' => $rowData['work_address'] ?? null,
            'department' => $rowData['department'] ?? null,
            'job_title' => $rowData['job_title'] ?? null,
            'shift_id' => $data['shift_id'] ?? null,
            'basic_salary' => $basicSalary,
            'allowances' => $allowances,
            'allowance_housing' => isset($rowData['allowance_housing']) && $rowData['allowance_housing'] !== '' && is_numeric($rowData['allowance_housing']) ? (float) $rowData['allowance_housing'] : null,
            'allowance_transportation' => isset($rowData['allowance_transportation']) && $rowData['allowance_transportation'] !== '' && is_numeric($rowData['allowance_transportation']) ? (float) $rowData['allowance_transportation'] : null,
            'allowance_other' => isset($rowData['allowance_other']) && $rowData['allowance_other'] !== '' && is_numeric($rowData['allowance_other']) ? (float) $rowData['allowance_other'] : null,
            'allowance_food' => isset($rowData['allowance_food']) && $rowData['allowance_food'] !== '' && is_numeric($rowData['allowance_food']) ? (float) $rowData['allowance_food'] : null,
            'allowance_personal_car' => isset($rowData['allowance_personal_car']) && $rowData['allowance_personal_car'] !== '' && is_numeric($rowData['allowance_personal_car']) ? (float) $rowData['allowance_personal_car'] : null,
            'manager' => $rowData['manager'] ?? null,
            'direct_manager' => $rowData['direct_manager'] ?? null,
            'additional_approver_2' => $rowData['additional_approver_2'] ?? null,
            'additional_approver_3' => $rowData['additional_approver_3'] ?? null,
            'hire_date' => ! empty($rowData['hire_date']) ? $rowData['hire_date'] : null,
            'employment_date' => ! empty($rowData['employment_date']) ? $rowData['employment_date'] : null,
            'probation_end_date' => ! empty($rowData['probation_end_date']) ? $rowData['probation_end_date'] : null,
            'employment_status' => $rowData['employment_status'] ?? 'active',
            'termination_date' => ! empty($rowData['termination_date']) ? $rowData['termination_date'] : null,
            'departure_date' => ! empty($rowData['departure_date']) ? $rowData['departure_date'] : null,
            'departure_reason' => $rowData['departure_reason'] ?? null,
            'id_number' => $rowData['id_number'] ?? null,
            'residence_expiry_date' => ! empty($rowData['residence_expiry_date']) ? $rowData['residence_expiry_date'] : null,
            'contract_end_date' => ! empty($rowData['contract_end_date']) ? $rowData['contract_end_date'] : null,
            'exit_reentry_visa_expiry' => ! empty($rowData['exit_reentry_visa_expiry']) ? $rowData['exit_reentry_visa_expiry'] : null,
            'passport_number' => $rowData['passport_number'] ?? null,
            'passport_expiry_date' => ! empty($rowData['passport_expiry_date']) ? $rowData['passport_expiry_date'] : null,
            'insurance_policy' => $rowData['insurance_policy'] ?? null,
            'insurance_expiry_date' => ! empty($rowData['insurance_expiry_date']) ? $rowData['insurance_expiry_date'] : null,
            'emergency_contact_name' => $rowData['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $rowData['emergency_contact_phone'] ?? null,
            'emergency_contact_email' => $rowData['emergency_contact_email'] ?? null,
            'emergency_contact_address' => $rowData['emergency_contact_address'] ?? null,
            'notes' => $rowData['notes'] ?? null,
        ]);

        return ['errors' => [], 'data' => $data];
    }

    /**
     * Import employees from validated data.
     */
    public function importEmployees(array $validatedData, Company $company, User $user): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($validatedData as $data) {
                if (! empty($data['id'])) {
                    $employeeId = (int) $data['id'];
                    unset($data['id']);

                    $employee = Employee::query()
                        ->where('id', $employeeId)
                        ->where('company_id', $company->id)
                        ->first();

                    if (! $employee) {
                        $errors[] = "Employee with ID {$employeeId} not found for update.";

                        continue;
                    }

                    $payload = $this->normalizePersistedEmployeeAttributes($data);
                    $employee->update($payload);
                    $updated++;
                } else {
                    unset($data['id']);
                    Employee::create($this->normalizePersistedEmployeeAttributes($data));
                    $created++;
                }
            }

            DB::commit();

            return [
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
                'total' => $created + $updated,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate summary of import data.
     */
    protected function generateSummary(array $data, string $importMode = self::IMPORT_MODE_CREATE): array
    {
        $total = count($data);

        if ($importMode === self::IMPORT_MODE_UPDATE) {
            return [
                'total_records' => $total,
                'new_records' => 0,
                'update_records' => $total,
            ];
        }

        $newRecords = 0;
        $updateRecords = 0;

        foreach ($data as $record) {
            if (! empty($record['id'])) {
                $updateRecords++;
            } else {
                $newRecords++;
            }
        }

        return [
            'total_records' => $total,
            'new_records' => $newRecords,
            'update_records' => $updateRecords,
        ];
    }

    /**
     * Parse a date from CSV/Excel: DD/MM/YYYY (e.g. 15/07/1986), YYYY-MM-DD, or other values Carbon accepts.
     */
    protected function parseImportDateString(string $raw): ?\Carbon\Carbon
    {
        $s = trim($raw);
        if (str_starts_with($s, self::UTF8_BOM)) {
            $s = trim(substr($s, strlen(self::UTF8_BOM)));
        }
        if ($s === '') {
            return null;
        }

        // Unambiguous day/month/year (common Excel regional format).
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return \Carbon\Carbon::createFromDate($year, $month, $day)->startOfDay();
            }

            return null;
        }

        // ISO YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return \Carbon\Carbon::createFromDate($year, $month, $day)->startOfDay();
            }

            return null;
        }

        // Excel serial day number (1900 date system), e.g. 45192 — avoid Carbon mis-parsing as timestamp.
        if (preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            $n = (float) $s;
            if ($n >= 20000.0 && $n <= 120000.0) {
                $unix = (int) round(($n - 25569.0) * 86400.0);
                if ($unix > 0) {
                    return \Carbon\Carbon::createFromTimestamp($unix, 'UTC')->startOfDay();
                }
            }
        }

        try {
            return \Carbon\Carbon::parse($s)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Keep only fillable attributes and re-normalize date strings after JSON storage (execute step).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePersistedEmployeeAttributes(array $data): array
    {
        $data = Arr::only($data, (new Employee)->getFillable());

        foreach (self::EMPLOYEE_IMPORT_DATE_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $v = $data[$field];
            if ($v === null) {
                continue;
            }
            if ($v === '') {
                $data[$field] = null;

                continue;
            }
            if (! is_scalar($v)) {
                continue;
            }

            $parsed = $this->parseImportDateString((string) $v);
            if ($parsed !== null) {
                $data[$field] = $parsed->format('Y-m-d');

                continue;
            }

            try {
                $data[$field] = \Carbon\Carbon::parse((string) $v)->format('Y-m-d');
            } catch (\Throwable $e) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * Validate date format.
     */
    protected function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Find nationality ID by name.
     */
    protected function findNationalityId(string $name, array $nationalities): ?int
    {
        $key = mb_strtolower($name);
        return $nationalities[$key] ?? null;
    }

    /**
     * Find country ID by name.
     */
    protected function findCountryId(string $name, array $countries): ?int
    {
        $key = mb_strtolower($name);
        return $countries[$key] ?? null;
    }

    /**
     * Find department ID by name.
     */
    protected function findDepartmentId(string $name, array $departments): ?int
    {
        foreach ($departments as $id => $department) {
            if (strcasecmp($department, $name) === 0) {
                return $id;
            }
        }
        return null;
    }
} 