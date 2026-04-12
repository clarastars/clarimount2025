<?php

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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class EmployeeImportService
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

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
            'email',
            'personal_email',
            'work_email',
            'phone',
            'work_phone',
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

        $sampleData = [
            '',
            'EMP001',
            'John',
            'Doe',
            'Michael',
            'American',
            'Saudi Arabia',
            '1990-01-15',
            'john.doe@example.com',
            'john.personal@example.com',
            'john.work@company.com',
            '+1234567890',
            '+1234567891',
            '+1234567892',
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
            'email',
            'personal_email',
            'work_email',
            'phone',
            'work_phone',
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
                $employee->email,
                $employee->personal_email,
                $employee->work_email,
                $employee->phone,
                $employee->work_phone,
                $employee->mobile,
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
     * Validate CSV file and return structured data.
     */
    public function validateCsv(string $filePath, Company $company): array
    {
        try {
            $csvData = $this->parseCsvFile($filePath);
            
            if (empty($csvData)) {
                return [
                    'success' => false,
                    'errors' => ['The CSV file is empty or could not be parsed.'],
                ];
            }

            $headers = array_shift($csvData);
            $validatedData = [];
            $errors = [];
            $rowNumber = 2; // Start from row 2 (after headers)

            // Validate headers (email is optional)
            $requiredHeaders = ['first_name', 'last_name'];
            $missingHeaders = array_diff($requiredHeaders, $headers);
            if (!empty($missingHeaders)) {
                return [
                    'success' => false,
                    'errors' => ['Missing required headers: ' . implode(', ', $missingHeaders)],
                ];
            }

            // Get lookup data for validation
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
            $existingEmployees = Employee::where('company_id', $company->id)->pluck('email', 'id')->toArray();

            foreach ($csvData as $row) {
                $rowData = array_combine($headers, $row);
                $validationResult = $this->validateRow($rowData, $rowNumber, $company, $countries, $nationalities, $departments, $shiftIds, $existingEmployees);
                
                if (!empty($validationResult['errors'])) {
                    $errors = array_merge($errors, $validationResult['errors']);
                } else {
                    $validatedData[] = $validationResult['data'];
                }

                $rowNumber++;
            }

            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors,
                ];
            }

            // Generate summary
            $summary = $this->generateSummary($validatedData);

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
     * Parse CSV file into array.
     */
    protected function parseCsvFile(string $filePath): array
    {
        $fullPath = Storage::disk('local')->path($filePath);
        $csvData = [];

        if (($handle = fopen($fullPath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $csvData[] = $row;
            }
            fclose($handle);
        }

        // Strip UTF-8 BOM from first header cell (present when re-importing Excel-exported CSV).
        if (!empty($csvData[0][0]) && str_starts_with($csvData[0][0], self::UTF8_BOM)) {
            $csvData[0][0] = substr($csvData[0][0], strlen(self::UTF8_BOM));
        }

        return $csvData;
    }

    /**
     * Validate a single row of CSV data.
     */
    protected function validateRow(array $rowData, int $rowNumber, Company $company, array $countries, array $nationalities, array $departments, array $shiftIds, array $existingEmployees): array
    {
        $errors = [];
        $data = [];

        // Check if this is an update (has ID) or new record
        $isUpdate = !empty($rowData['id']) && is_numeric($rowData['id']);
        $existingEmployee = null;
        
        if ($isUpdate) {
            $existingEmployee = Employee::where('id', $rowData['id'])
                ->where('company_id', $company->id)
                ->first();
            
            if (!$existingEmployee) {
                $errors[] = "Row {$rowNumber}: Employee with ID {$rowData['id']} not found.";
                return ['errors' => $errors, 'data' => null];
            }
        }

        // Validate required fields (email is optional)
        $requiredFields = ['first_name', 'last_name'];
        foreach ($requiredFields as $field) {
            if (empty(trim($rowData[$field] ?? ''))) {
                $errors[] = "Row {$rowNumber}: {$field} is required.";
            }
        }

        // Validate email format and uniqueness only when email is provided
        $email = isset($rowData['email']) ? trim($rowData['email']) : '';
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNumber}: Invalid email format.";
            } else {
                $existingEmailEmployee = Employee::where('email', $email)
                    ->where('company_id', $company->id)
                    ->first();
                if ($existingEmailEmployee && (!$isUpdate || $existingEmailEmployee->id !== $existingEmployee->id)) {
                    $errors[] = "Row {$rowNumber}: Email already exists for another employee.";
                }
            }
        }

        // Validate employment status
        if (!empty($rowData['employment_status']) && !in_array($rowData['employment_status'], ['active', 'inactive', 'terminated'])) {
            $errors[] = "Row {$rowNumber}: Invalid employment status. Must be: active, inactive, or terminated.";
        }

        // Validate date fields
        $dateFields = [
            'birth_date', 'hire_date', 'employment_date', 'probation_end_date',
            'termination_date', 'departure_date', 'residence_expiry_date',
            'contract_end_date', 'exit_reentry_visa_expiry', 'passport_expiry_date',
            'insurance_expiry_date'
        ];

        foreach ($dateFields as $field) {
            if (!empty($rowData[$field])) {
                try {
                    $parsed = \Carbon\Carbon::parse($rowData[$field]);
                    $rowData[$field] = $parsed->format('Y-m-d');
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNumber}: Invalid date format for {$field}.";
                }
            }
        }

        // Validate nationality
        if (!empty($rowData['nationality'])) {
            $nationalityId = $this->findNationalityId($rowData['nationality'], $nationalities);
            if (!$nationalityId) {
                $errors[] = "Row {$rowNumber}: Nationality '{$rowData['nationality']}' not found.";
            } else {
                $data['nationality_id'] = $nationalityId;
            }
        }

        // Validate residence country
        if (!empty($rowData['residence_country'])) {
            $countryId = $this->findCountryId($rowData['residence_country'], $countries);
            if (!$countryId) {
                $errors[] = "Row {$rowNumber}: Residence country '{$rowData['residence_country']}' not found.";
            } else {
                $data['residence_country_id'] = $countryId;
            }
        }

        // Validate department
        if (!empty($rowData['department'])) {
            $departmentId = $this->findDepartmentId($rowData['department'], $departments);
            if (!$departmentId) {
                $errors[] = "Row {$rowNumber}: Department '{$rowData['department']}' not found.";
            } else {
                $data['department_id'] = $departmentId;
            }
        }

        // Validate shift_id if provided
        if (isset($rowData['shift_id']) && $rowData['shift_id'] !== '' && $rowData['shift_id'] !== null) {
            $shiftId = is_numeric($rowData['shift_id']) ? (int) $rowData['shift_id'] : null;
            if ($shiftId === null || !in_array($shiftId, $shiftIds, true)) {
                $errors[] = "Row {$rowNumber}: Invalid or unknown shift_id.";
            } else {
                $data['shift_id'] = $shiftId;
            }
        }

        // Validate numeric salary/allowance fields
        $numericFields = [
            'basic_salary', 'allowances', 'allowance_housing', 'allowance_transportation',
            'allowance_other', 'allowance_food', 'allowance_personal_car',
        ];
        foreach ($numericFields as $field) {
            if (isset($rowData[$field]) && $rowData[$field] !== '' && $rowData[$field] !== null) {
                if (!is_numeric($rowData[$field])) {
                    $errors[] = "Row {$rowNumber}: {$field} must be a number.";
                }
            }
        }

        // If there are errors, return them
        if (!empty($errors)) {
            return ['errors' => $errors, 'data' => null];
        }

        // Build clean data array
        $basicSalary = isset($rowData['basic_salary']) && $rowData['basic_salary'] !== '' && is_numeric($rowData['basic_salary'])
            ? (float) $rowData['basic_salary'] : 0;
        $allowances = isset($rowData['allowances']) && $rowData['allowances'] !== '' && is_numeric($rowData['allowances'])
            ? (float) $rowData['allowances'] : 0;
        $data = array_merge($data, [
            'id' => $isUpdate ? $existingEmployee->id : null,
            'company_id' => $company->id,
            'employee_id' => $rowData['employee_id'] ?? null,
            'first_name' => $rowData['first_name'],
            'last_name' => $rowData['last_name'],
            'father_name' => $rowData['father_name'] ?? null,
            'birth_date' => !empty($rowData['birth_date']) ? $rowData['birth_date'] : null,
            'email' => $email !== '' ? $email : null,
            'personal_email' => $rowData['personal_email'] ?? null,
            'work_email' => $rowData['work_email'] ?? null,
            'phone' => $rowData['phone'] ?? null,
            'work_phone' => $rowData['work_phone'] ?? null,
            'mobile' => $rowData['mobile'] ?? null,
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
            'hire_date' => !empty($rowData['hire_date']) ? $rowData['hire_date'] : null,
            'employment_date' => !empty($rowData['employment_date']) ? $rowData['employment_date'] : null,
            'probation_end_date' => !empty($rowData['probation_end_date']) ? $rowData['probation_end_date'] : null,
            'employment_status' => $rowData['employment_status'] ?? 'active',
            'termination_date' => !empty($rowData['termination_date']) ? $rowData['termination_date'] : null,
            'departure_date' => !empty($rowData['departure_date']) ? $rowData['departure_date'] : null,
            'departure_reason' => $rowData['departure_reason'] ?? null,
            'id_number' => $rowData['id_number'] ?? null,
            'residence_expiry_date' => !empty($rowData['residence_expiry_date']) ? $rowData['residence_expiry_date'] : null,
            'contract_end_date' => !empty($rowData['contract_end_date']) ? $rowData['contract_end_date'] : null,
            'exit_reentry_visa_expiry' => !empty($rowData['exit_reentry_visa_expiry']) ? $rowData['exit_reentry_visa_expiry'] : null,
            'passport_number' => $rowData['passport_number'] ?? null,
            'passport_expiry_date' => !empty($rowData['passport_expiry_date']) ? $rowData['passport_expiry_date'] : null,
            'insurance_policy' => $rowData['insurance_policy'] ?? null,
            'insurance_expiry_date' => !empty($rowData['insurance_expiry_date']) ? $rowData['insurance_expiry_date'] : null,
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
                if (!empty($data['id'])) {
                    // Update existing employee
                    $employee = Employee::find($data['id']);
                    if ($employee) {
                        $employee->update($data);
                        $updated++;
                    } else {
                        $errors[] = "Employee with ID {$data['id']} not found for update.";
                    }
                } else {
                    // Create new employee
                    unset($data['id']);
                    Employee::create($data);
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
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Generate summary of import data.
     */
    protected function generateSummary(array $data): array
    {
        $newRecords = 0;
        $updateRecords = 0;

        foreach ($data as $record) {
            if (!empty($record['id'])) {
                $updateRecords++;
            } else {
                $newRecords++;
            }
        }

        return [
            'total_records' => count($data),
            'new_records' => $newRecords,
            'update_records' => $updateRecords,
        ];
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