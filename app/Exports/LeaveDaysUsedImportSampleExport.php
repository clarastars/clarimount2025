<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LeaveDaysUsedImportSampleExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Leave Days Used';
    }

    public function headings(): array
    {
        return [
            'id_number',
            'leave_days_used',
        ];
    }

    public function array(): array
    {
        return [
            ['1234567890', 2.5],
            ['0987654321', 1],
        ];
    }
}
