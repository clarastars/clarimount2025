<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    public const TYPE_IDENTITY = 'identity';

    public const TYPE_NATIONAL_ADDRESS = 'national_address';

    public const TYPE_QUALIFICATION = 'qualification';

    public const TYPE_CV = 'cv';

    public const TYPE_IBAN = 'iban';

    protected $fillable = [
        'employee_id',
        'type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_IDENTITY,
            self::TYPE_NATIONAL_ADDRESS,
            self::TYPE_QUALIFICATION,
            self::TYPE_CV,
            self::TYPE_IBAN,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
