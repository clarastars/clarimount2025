<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class LeaveAttachmentService
{
    public const DISK = 'public';

    public const DIRECTORY = 'leave-attachments';

    public const MAX_FILES = 10;

    public const MAX_KILOBYTES = 5120;

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return list<string>
     */
    public function storeFromRequest(Request $request): array
    {
        $files = [];

        if ($request->hasFile('attachments')) {
            $uploaded = $request->file('attachments');
            $files = is_array($uploaded) ? $uploaded : [$uploaded];
        } elseif ($request->hasFile('attachment')) {
            $files = [$request->file('attachment')];
        }

        $paths = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $paths[] = $file->store(self::DIRECTORY, self::DISK);
        }

        return array_values($paths);
    }

    /**
     * @param  list<string>  $paths
     * @return array{attachment_path: ?string, attachment_paths: ?list<string>}
     */
    public function persistPayload(array $paths): array
    {
        $paths = $this->normalizeStoredPaths(null, $paths);

        return [
            'attachment_path' => $paths[0] ?? null,
            'attachment_paths' => $paths === [] ? null : $paths,
        ];
    }

    /**
     * @return list<string>
     */
    public function normalizeStoredPaths(?string $legacyPath, mixed $paths): array
    {
        $normalized = [];

        if (is_array($paths)) {
            foreach ($paths as $path) {
                if (is_string($path) && $path !== '') {
                    $normalized[] = $path;
                }
            }
        }

        if ($legacyPath !== null && $legacyPath !== '' && ! in_array($legacyPath, $normalized, true)) {
            array_unshift($normalized, $legacyPath);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    public function publicUrls(array $paths): array
    {
        return array_values(array_map(
            static function (string $path): string {
                $filename = basename(str_replace('\\', '/', $path));

                return route('leave-attachments.show', ['filename' => $filename], false);
            },
            $paths,
        ));
    }
}
