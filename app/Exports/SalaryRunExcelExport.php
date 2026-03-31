<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\SalaryRun;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SalaryRunExcelExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, ShouldAutoSize
{
    use RegistersEventListeners;

    public function __construct(
        private SalaryRun $salaryRun
    ) {}

    public function collection()
    {
        return $this->salaryRun->items()->with('employee')->orderBy('id')->get();
    }

    public function headings(): array
    {
        return [
            'اسم الموظف',
            'الراتب الأساسي',
            'بدل سكن',
            'بدل مواصلات',
            'بدل انتقالات',
            'بدلات أخرى',
            'بدل طعام',
            'بدل استخدام سيارة شخصية',
            'بدلات إضافية / مستحقات شهرية',
            'انقطاع ساعات عمل',
            'ذمم',
            'مخالفات مرورية تحمل حادث',
            'جزاءات',
            'خصم تأمينات',
            'غيابات',
            'تصديقات',
            'صافي الراتب للدفع',
        ];
    }

    public function map($item): array
    {
        $employee = $item->employee;
        $employeeName = '';
        if ($employee) {
            $parts = [
                trim((string) ($employee->first_name ?? '')),
                trim((string) ($employee->father_name ?? '')),
                trim((string) ($employee->last_name ?? '')),
            ];
            $employeeName = implode(' ', array_filter($parts, static fn (string $p): bool => $p !== ''));
        }
        $debtTotal = 0;
        if (is_array($item->debt_deductions)) {
            foreach ($item->debt_deductions as $d) {
                $debtTotal += (float) ($d['amount'] ?? 0);
            }
        }

        $housing = $employee && $employee->allowance_housing !== null ? (float) $employee->allowance_housing : 0;
        $transport = $employee && $employee->allowance_transportation !== null ? (float) $employee->allowance_transportation : 0;
        $other = $employee && $employee->allowance_other !== null ? (float) $employee->allowance_other : 0;
        $food = $employee && $employee->allowance_food !== null ? (float) $employee->allowance_food : 0;
        $personalCar = $employee && $employee->allowance_personal_car !== null ? (float) $employee->allowance_personal_car : 0;
        $itemAllowances = $item->allowances !== null ? (float) $item->allowances : 0;
        $detailedSum = $housing + $transport + $other + $food + $personalCar;
        $additionalAllowances = $itemAllowances > $detailedSum ? round($itemAllowances - $detailedSum, 2) : '';

        return [
            $employeeName,
            $item->basic_salary !== null ? (float) $item->basic_salary : '',
            $housing ?: '',
            $transport ?: '',
            '', // بدل انتقالات - لا يوجد في النظام
            $other ?: '',
            $food ?: '',
            $personalCar ?: '',
            $additionalAllowances,
            $item->unpaid_leave_total !== null ? (float) $item->unpaid_leave_total : '',
            $debtTotal > 0 ? $debtTotal : '',
            '', // مخالفات مرورية تحمل حادث - لا يوجد في النظام
            $item->penalties_total !== null ? (float) $item->penalties_total : '',
            '', // خصم تأمينات - لا يوجد في النظام
            '', // غيابات - منفصل عن انقطاع ساعات إن لم يكن لدينا حقل
            '', // تصديقات - لا يوجد في النظام
            $item->net_salary !== null ? (float) $item->net_salary : '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2EFDA'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ],
        ];
    }

    public static function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        if ($highestRow > 0 && $highestColumn) {
            $range = 'A1:' . $highestColumn . $highestRow;
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Add totals row for all amount columns.
        if ($highestRow >= 2) {
            $totalRow = $highestRow + 1;
            $sheet->setCellValue('A' . $totalRow, 'الإجمالي');

            foreach (range('B', 'Q') as $column) {
                $sheet->setCellValue(
                    $column . $totalRow,
                    sprintf('=SUM(%s2:%s%d)', $column, $column, $highestRow)
                );
            }

            $totalRange = 'A' . $totalRow . ':Q' . $totalRow;
            $sheet->getStyle($totalRange)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF2CC'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }
    }
}
