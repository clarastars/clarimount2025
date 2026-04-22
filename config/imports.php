<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Employee Import File Limits
    |--------------------------------------------------------------------------
    |
    | Maximum allowed upload size for employee CSV import in kilobytes.
    | Example: 51200 = 50MB.
    |
    */
    'employee_file_max_kb' => (int) env('EMPLOYEE_IMPORT_MAX_KB', 51200),
];

