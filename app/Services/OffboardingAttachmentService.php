<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OffboardingAttachmentService
{
    public const DIRECTORY = 'offboarding-attachments';

    public const MAX_KILOBYTES = 5120;

    public function diskName(): string
    {
        return (string) config('filesystems.cloud', 's3');
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(bool $required = false): array
    {
        return [
            'attachment' => [
                $required ? 'required' : 'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.self::MAX_KILOBYTES,
            ],
        ];
    }

    public function store(UploadedFile $file, int $employeeId, int $caseId): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        if ($safeName === '') {
            $safeName = 'attachment';
        }

        $filename = $safeName.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6))
            .($extension !== '' ? '.'.$extension : '');

        $path = $file->storeAs(
            self::DIRECTORY.'/'.$employeeId.'/'.$caseId,
            $filename,
            [
                'disk' => $this->diskName(),
                'visibility' => 'private',
            ],
        );

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException(__('messages.offboarding.attachment_upload_failed'));
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        try {
            Storage::disk($this->diskName())->delete($path);
        } catch (\Throwable) {
            // Best-effort cleanup.
        }
    }

    public function temporaryUrl(?string $path, int $minutes = 30): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        try {
            return Storage::disk($this->diskName())->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            try {
                return Storage::disk($this->diskName())->url($path);
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
