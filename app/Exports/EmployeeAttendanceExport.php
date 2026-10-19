<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeAttendanceExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    private const TZ = 'Asia/Riyadh';

    /**
     * @param  Collection<int, object>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly string $employeeName,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * Column A is the rightmost column when the sheet is RTL:
     * اسم الموظف | وقت الحضور | وقت الانصراف | التاريخ
     */
    public function headings(): array
    {
        return [
            __('messages.employees.export_attendance_employee_name'),
            __('messages.employees.export_attendance_check_in'),
            __('messages.employees.export_attendance_check_out'),
            __('messages.employees.export_attendance_date'),
        ];
    }

    /**
     * @param  object{att_date: mixed, first_punch: mixed, last_punch: mixed}  $row
     */
    public function map($row): array
    {
        return [
            $this->employeeName,
            $this->formatTime($row->first_punch ?? null),
            $this->formatTime($row->last_punch ?? null),
            $this->formatDate($row->att_date ?? null),
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
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $event->sheet->getDelegate()->setRightToLeft(true);
            },
        ];
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse((string) $value, self::TZ)->format('Y-m-d');
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse((string) $value)->timezone(self::TZ)->format('H:i:s');
    }
}
