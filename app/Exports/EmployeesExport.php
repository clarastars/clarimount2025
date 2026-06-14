<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Employee;
use App\Services\EmployeeImportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    use RegistersEventListeners;

    /** @var list<string> Columns stored as text so Excel does not corrupt IDs and phone numbers. */
    private const TEXT_COLUMNS = [
        'B',  // employee_id
        'K',  // personal_phone
        'L',  // work_phone
        'M',  // fingerprint_device_id
        'AJ', // id_number
        'AN', // passport_number
        'AS', // emergency_contact_phone
    ];

    public function __construct(
        private readonly Collection $employees,
        private readonly EmployeeImportService $importService,
    ) {}

    public function collection(): Collection
    {
        return $this->employees;
    }

    public function headings(): array
    {
        return $this->importService->exportColumnHeaders();
    }

    /**
     * @param  Employee  $employee
     */
    public function map($employee): array
    {
        return $this->importService->mapEmployeeToExportRow($employee);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public static function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        if ($highestRow < 1 || ! $highestColumn) {
            return;
        }

        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$highestColumn.'1');

        $dataRange = 'A1:'.$highestColumn.$highestRow;
        $sheet->getStyle($dataRange)->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        $sheet->getStyle('A2:'.$highestColumn.$highestRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        if ($highestRow >= 2) {
            for ($row = 2; $row <= $highestRow; $row++) {
                foreach (self::TEXT_COLUMNS as $column) {
                    $cell = $sheet->getCell($column.$row);
                    $cell->setValueExplicit((string) $cell->getValue(), DataType::TYPE_STRING);
                }
            }

            foreach (self::TEXT_COLUMNS as $column) {
                $sheet->getStyle($column.'2:'.$column.$highestRow)
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
        }

        $sheet->getRowDimension(1)->setRowHeight(28);
        for ($row = 2; $row <= $highestRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(-1);
        }
    }
}
