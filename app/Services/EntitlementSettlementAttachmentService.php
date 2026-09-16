<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EntitlementSettlementAttachmentService
{
    public const DIRECTORY = 'entitlement-settlement-attachments';

    public const MAX_FILES = 10;

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
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES],
            'remove_attachment_paths' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'remove_attachment_paths.*' => ['string', 'max:500'],
        ];
    }

    /**
     * @return list<string>
     */
    public function storeFromRequest(Request $request, int $employeeId): array
    {
        $files = [];

        if ($request->hasFile('attachments')) {
            $uploaded = $request->file('attachments');
            $files = is_array($uploaded) ? $uploaded : [$uploaded];
        }

        $paths = [];
        $diskName = $this->diskName();
        $directory = self::DIRECTORY.'/'.$employeeId;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            if ($safeName === '') {
                $safeName = 'attachment';
            }

            $filename = $safeName.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6))
                .($extension !== '' ? '.'.$extension : '');

            $paths[] = $file->storeAs(
                $directory,
                $filename,
                [
                    'disk' => $diskName,
                    'visibility' => 'private',
                ],
            );
        }

        return array_values(array_filter($paths, static fn ($path): bool => is_string($path) && $path !== ''));
    }

    /**
     * @param  list<string>|null  $existingPaths
     * @param  list<string>  $newPaths
     * @param  list<string>  $removePaths
     * @return list<string>
     */
    public function mergePaths(?array $existingPaths, array $newPaths, array $removePaths = []): array
    {
        $existing = $this->normalizeStoredPaths($existingPaths);
        $remove = $this->normalizeStoredPaths($removePaths);

        foreach ($remove as $path) {
            if (! in_array($path, $existing, true)) {
                continue;
            }

            $existing = array_values(array_filter(
                $existing,
                static fn (string $item): bool => $item !== $path,
            ));
            $this->deleteStoredPath($path);
        }

        $merged = array_values(array_unique([...$existing, ...$newPaths]));

        if (count($merged) > self::MAX_FILES) {
            $merged = array_slice($merged, 0, self::MAX_FILES);
        }

        return $merged;
    }

    /**
     * @param  list<string>|null  $paths
     * @return list<string>
     */
    public function normalizeStoredPaths(mixed $paths): array
    {
        if (! is_array($paths)) {
            return [];
        }

        $normalized = [];

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                $normalized[] = $path;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<string>  $paths
     * @return list<array{path: string, url: string, name: string}>
     */
    public function publicAttachmentPayload(array $paths, int $employeeId, int $settlementId): array
    {
        return array_values(array_map(
            static function (string $path) use ($employeeId, $settlementId): array {
                $filename = basename(str_replace('\\', '/', $path));

                return [
                    'path' => $path,
                    'name' => $filename,
                    'url' => route('employees.entitlement-settlement.attachments.show', [
                        'employee' => $employeeId,
                        'entitlementSettlement' => $settlementId,
                        'filename' => $filename,
                    ], false),
                ];
            },
            $this->normalizeStoredPaths($paths),
        ));
    }

    public function deleteStoredPath(string $path): void
    {
        $normalized = str_replace('\\', '/', $path);
        if ($path === '' || ! str_starts_with($normalized, self::DIRECTORY.'/')) {
            return;
        }

        $disk = Storage::disk($this->diskName());
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    /**
     * @param  list<string>|null  $paths
     */
    public function deleteStoredPaths(?array $paths): void
    {
        foreach ($this->normalizeStoredPaths($paths) as $path) {
            $this->deleteStoredPath($path);
        }
    }

    /**
     * @param  list<string>  $ownedPaths
     */
    public function resolveOwnedPath(array $ownedPaths, string $filename): ?string
    {
        if (preg_match('/^[A-Za-z0-9._-]+$/', $filename) !== 1) {
            return null;
        }

        $disk = Storage::disk($this->diskName());

        foreach ($this->normalizeStoredPaths($ownedPaths) as $path) {
            if (basename(str_replace('\\', '/', $path)) !== $filename) {
                continue;
            }

            if ($disk->exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
