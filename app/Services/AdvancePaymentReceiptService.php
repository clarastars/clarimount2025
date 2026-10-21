<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdvancePaymentReceiptService
{
    public const DIRECTORY = 'advance-payment-receipts';

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

    public function store(UploadedFile $file, int $employeeId, int $advanceRequestId): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        if ($safeName === '') {
            $safeName = 'receipt';
        }

        $filename = $safeName.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6))
            .($extension !== '' ? '.'.$extension : '');

        $directory = self::DIRECTORY.'/'.$employeeId.'/'.$advanceRequestId;

        return $file->storeAs(
            $directory,
            $filename,
            [
                'disk' => $this->diskName(),
                'visibility' => 'private',
            ],
        );
    }

    public function temporaryUrl(?string $path, int $minutes = 15): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk($this->diskName());

        if (! $disk->exists($path)) {
            return null;
        }

        try {
            return $disk->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            return $disk->url($path);
        }
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
