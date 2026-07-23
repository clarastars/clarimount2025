<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesMissingHireDateSummarySheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, array{company_id: int|null, company_name_en: string, company_name_ar: string, employees_count: int}>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly int $totalEmployees,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function headings(): array
    {
        return [
            'Company ID',
            'Company EN',
            'Company AR',
            'Employees Without Hire Date',
            'Total Across All Companies',
        ];
    }

    /**
     * @param  array{company_id: int|null, company_name_en: string, company_name_ar: string, employees_count: int}  $row
     */
    public function map($row): array
    {
        return [
            $row['company_id'],
            $row['company_name_en'],
            $row['company_name_ar'],
            $row['employees_count'],
            $this->totalEmployees,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2F5496'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
