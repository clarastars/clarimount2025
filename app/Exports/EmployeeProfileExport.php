<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Employee;
use App\Services\ManualDeductionAmountService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeProfileExport implements FromArray, ShouldAutoSize, WithEvents, WithStyles
{
    /** @var list<string> */
    public const ALLOWED_FIELDS = [
        'full_name',
        'id_number',
        'work_phone',
        'work_email',
        'basic_salary',
        'gross_salary',
        'job_title',
        'company',
        'department',
        'nationality',
        'hire_date',
    ];

    /** @var list<string> */
    private const TEXT_FIELDS = [
        'id_number',
        'work_phone',
        'work_email',
    ];

    /** @var list<string> */
    private array $fields;

    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        private readonly Employee $employee,
        array $fields,
        private readonly ManualDeductionAmountService $amountService,
    ) {
        $allowed = array_flip(self::ALLOWED_FIELDS);
        $normalized = [];

        foreach ($fields as $field) {
            if (! is_string($field)) {
                continue;
            }

            if (isset($allowed[$field]) && ! in_array($field, $normalized, true)) {
                $normalized[] = $field;
            }
        }

        $this->fields = $normalized !== [] ? $normalized : self::ALLOWED_FIELDS;
    }

    /**
     * @return list<string>
     */
    public static function allowedFields(): array
    {
        return self::ALLOWED_FIELDS;
    }

    public function array(): array
    {
        $headers = [];
        $values = [];

        foreach ($this->fields as $field) {
            $headers[] = __('messages.employees.export_profile_fields.'.$field);
            $values[] = $this->resolveFieldValue($field);
        }

        return [
            $headers,
            $values,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A8A'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            2 => [
                'font' => ['size' => 11],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $columnCount = count($this->fields);
                $highestColumn = Coordinate::stringFromColumnIndex($columnCount);

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');
                $sheet->getRowDimension(1)->setRowHeight(32);
                $sheet->getRowDimension(2)->setRowHeight(28);

                $range = 'A1:'.$highestColumn.'2';
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                ]);

                $sheet->getStyle('A2:'.$highestColumn.'2')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ],
                ]);

                foreach ($this->fields as $index => $field) {
                    $column = Coordinate::stringFromColumnIndex($index + 1);

                    if (in_array($field, self::TEXT_FIELDS, true)) {
                        $cell = $sheet->getCell($column.'2');
                        $cell->setValueExplicit((string) $cell->getValue(), DataType::TYPE_STRING);
                        $sheet->getStyle($column.'2')
                            ->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_TEXT);
                    }

                    if (in_array($field, ['basic_salary', 'gross_salary'], true)) {
                        $sheet->getStyle($column.'2')
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                    }
                }
            },
        ];
    }

    private function resolveFieldValue(string $field): string|float|int|null
    {
        $employee = $this->employee;

        return match ($field) {
            'full_name' => (string) ($employee->full_name ?? ''),
            'id_number' => (string) ($employee->id_number ?? ''),
            'work_phone' => (string) ($employee->work_phone ?? ''),
            'work_email' => (string) ($employee->work_email ?? ''),
            'basic_salary' => round((float) ($employee->basic_salary ?? 0), 2),
            'gross_salary' => $this->amountService->grossMonthly($employee),
            'job_title' => (string) ($employee->job_title ?? ''),
            'company' => (string) ($employee->company?->name_ar ?: $employee->company?->name_en ?: ''),
            'department' => $this->resolveDepartmentLabel($employee),
            'nationality' => (string) ($employee->nationality?->name ?? ''),
            'hire_date' => $employee->hire_date?->format('Y-m-d') ?? '',
            default => '',
        };
    }

    private function resolveDepartmentLabel(Employee $employee): string
    {
        if ($employee->department instanceof \App\Models\Department) {
            return (string) $employee->department->name;
        }

        $legacy = $employee->getAttributes()['department'] ?? null;

        return is_string($legacy) ? $legacy : '';
    }
}
