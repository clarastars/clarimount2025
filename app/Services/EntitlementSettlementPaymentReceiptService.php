<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EntitlementSettlementPaymentReceiptService
{
    public const DIRECTORY = 'entitlement-settlement-payment-receipts';

    public const MAX_KILOBYTES = 5120;

    public function diskName(): string
    {
        return (string) config('filesystems.cloud', 's3');
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES],
            'payment_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function store(UploadedFile $file, int $employeeId, int $settlementId): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        if ($safeName === '') {
            $safeName = 'receipt';
        }

        $filename = $safeName.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6))
            .($extension !== '' ? '.'.$extension : '');

        $directory = self::DIRECTORY.'/'.$employeeId.'/'.$settlementId;

        return $file->storeAs(
            $directory,
            $filename,
            [
                'disk' => $this->diskName(),
                'visibility' => 'private',
            ],
        );
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk($this->diskName());

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
