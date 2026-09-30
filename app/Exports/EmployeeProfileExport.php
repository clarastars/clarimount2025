<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Concerns\ResolvesEmployeeProfileExportFields;
use App\Models\Employee;
use App\Services\ManualDeductionAmountService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeProfileExport extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithStyles
{
    use ResolvesEmployeeProfileExportFields;

    /** @var list<string> */
    private array $fields;

    private EmployeeProfileExportValueBinder $valueBinder;

    /**
     * @param  list<string>  $fields
     */
    public function __construct(
        private readonly Employee $employee,
        array $fields,
        private readonly ManualDeductionAmountService $amountService,
    ) {
        $this->fields = self::normalizeFields($fields);
        $this->valueBinder = new EmployeeProfileExportValueBinder;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        return $this->valueBinder->bindValue($cell, $value);
    }

    public function array(): array
    {
        $values = [];

        foreach ($this->fields as $field) {
            $values[] = $this->resolveFieldValue($this->employee, $field, $this->amountService);
        }

        return [
            $this->fieldHeaders(),
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
                        $sheet->getStyle($column.'2')
                            ->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_TEXT);
                    }

                    if (in_array($field, self::MONEY_FIELDS, true)) {
                        $sheet->getStyle($column.'2')
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                    }
                }
            },
        ];
    }
}
