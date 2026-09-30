<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Concerns\ResolvesEmployeeProfileExportFields;
use App\Models\Employee;
use App\Services\ManualDeductionAmountService;
use Illuminate\Support\Collection;
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

class CompanyEmployeesProfileExport implements FromArray, ShouldAutoSize, WithEvents, WithStyles
{
    use ResolvesEmployeeProfileExportFields;

    /** @var list<string> */
    private array $fields;

    /** @var Collection<int, Employee> */
    private Collection $employees;

    /**
     * @param  Collection<int, Employee>|iterable<int, Employee>  $employees
     * @param  list<string>  $fields
     */
    public function __construct(
        iterable $employees,
        array $fields,
        private readonly ManualDeductionAmountService $amountService,
    ) {
        $this->employees = $employees instanceof Collection
            ? $employees->values()
            : collect($employees)->values();
        $this->fields = self::normalizeFields($fields);
    }

    public function array(): array
    {
        $rows = [$this->fieldHeaders()];

        foreach ($this->employees as $employee) {
            $values = [];

            foreach ($this->fields as $field) {
                $values[] = $this->resolveFieldValue($employee, $field, $this->amountService);
            }

            $rows[] = $values;
        }

        return $rows;
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
                $lastRow = max(1, $this->employees->count() + 1);

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');
                $sheet->getRowDimension(1)->setRowHeight(32);

                $range = 'A1:'.$highestColumn.$lastRow;
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                if ($lastRow >= 2) {
                    $sheet->getStyle('A2:'.$highestColumn.$lastRow)->applyFromArray([
                        'font' => ['size' => 11],
                    ]);

                    for ($row = 2; $row <= $lastRow; $row++) {
                        $sheet->getRowDimension($row)->setRowHeight(26);

                        if ($row % 2 === 0) {
                            $sheet->getStyle('A'.$row.':'.$highestColumn.$row)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F8FAFC'],
                                ],
                            ]);
                        }
                    }
                }

                foreach ($this->fields as $index => $field) {
                    $column = Coordinate::stringFromColumnIndex($index + 1);

                    if ($lastRow < 2) {
                        continue;
                    }

                    if (in_array($field, self::TEXT_FIELDS, true)) {
                        for ($row = 2; $row <= $lastRow; $row++) {
                            $cell = $sheet->getCell($column.$row);
                            $cell->setValueExplicit((string) $cell->getValue(), DataType::TYPE_STRING);
                        }
                        $sheet->getStyle($column.'2:'.$column.$lastRow)
                            ->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_TEXT);
                    }

                    if (in_array($field, self::MONEY_FIELDS, true)) {
                        $sheet->getStyle($column.'2:'.$column.$lastRow)
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                    }
                }
            },
        ];
    }
}
