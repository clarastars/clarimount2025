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

class EmployeesMissingHireDateCompanySheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $sheetTitle,
        private readonly Collection $employees,
    ) {}

    public function collection(): Collection
    {
        return $this->employees;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Employee No',
            'First Name',
            'Last Name',
            'Company ID',
            'Company EN',
            'Company AR',
            'Employment Status',
            'Work Email',
            'Personal Email',
            'Annual Leave Entitlement',
            'Accrued Leave Balance',
            'Created At',
        ];
    }

    /**
     * @param  object{id:int,employee_id:?string,first_name:?string,last_name:?string,company_id:?int,company_name_en:?string,company_name_ar:?string,employment_status:?string,work_email:?string,personal_email:?string,annual_leave_balance:?float|int,leave_accrued_balance:?float|int,created_at:?string}  $row
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->employee_id,
            $row->first_name,
            $row->last_name,
            $row->company_id,
            $row->company_name_en,
            $row->company_name_ar,
            $row->employment_status,
            $row->work_email,
            $row->personal_email,
            $row->annual_leave_balance,
            $row->leave_accrued_balance,
            $row->created_at,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
