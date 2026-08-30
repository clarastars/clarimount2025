<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\SalaryRun;
use App\Models\SalaryRunItem;
use App\Services\SalaryRunExportRowBuilder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalaryRunExcelExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    use RegistersEventListeners;

    public function __construct(
        private SalaryRun $salaryRun,
        private SalaryRunExportRowBuilder $rowBuilder = new SalaryRunExportRowBuilder,
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
        /** @var SalaryRunItem $item */
        if ($this->salaryRun->isFinalized()) {
            return $this->mapFinalizedItem($item);
        }

        return $this->rowBuilder->snapshotToExcelRow(
            $this->rowBuilder->buildLiveSnapshot($item, $item->employee)
        );
    }

    /**
     * @return array<int, float|string>
     */
    private function mapFinalizedItem(SalaryRunItem $item): array
    {
        $snapshot = is_array($item->export_snapshot) ? $item->export_snapshot : null;

        if ($snapshot === null) {
            $snapshot = $this->rowBuilder->buildFrozenSnapshotFromItem($item, $item->employee);
            $item->forceFill(['export_snapshot' => $snapshot])->save();
        }

        return $this->rowBuilder->snapshotToExcelRow($snapshot);
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
            $range = 'A1:'.$highestColumn.$highestRow;
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        if ($highestRow >= 2) {
            $totalRow = $highestRow + 1;
            $sheet->setCellValue('A'.$totalRow, 'الإجمالي');

            foreach (range('B', 'Q') as $column) {
                $sheet->setCellValue(
                    $column.$totalRow,
                    sprintf('=SUM(%s2:%s%d)', $column, $column, $highestRow)
                );
            }

            $totalRange = 'A'.$totalRow.':Q'.$totalRow;
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
