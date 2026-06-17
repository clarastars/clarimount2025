<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StorageTestController extends Controller
{
    private const TEST_PREFIX = 'test-uploads';

    public function index(Request $request): Response
    {
        $diskName = (string) config('filesystems.cloud', 's3');
        $disk = Storage::disk($diskName);

        $files = collect($disk->files(self::TEST_PREFIX))
            ->map(fn (string $path) => $this->filePayload($diskName, $path))
            ->sortByDesc('last_modified')
            ->values()
            ->all();

        return Inertia::render('StorageTest/Index', [
            'files' => $files,
            'disk' => $diskName,
            'bucket' => config('filesystems.disks.' . $diskName . '.bucket'),
            'endpoint' => config('filesystems.disks.' . $diskName . '.endpoint'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
        ]);

        $diskName = (string) config('filesystems.cloud', 's3');

        $validated['file']->store(self::TEST_PREFIX, [
            'disk' => $diskName,
            'visibility' => config('filesystems.disks.' . $diskName . '.visibility', 'public'),
        ]);

        return redirect()
            ->route('storage-test.index')
            ->with('status', __('messages.storage_test.uploaded'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        $diskName = (string) config('filesystems.cloud', 's3');
        $disk = Storage::disk($diskName);
        $path = $validated['path'];

        if (! str_starts_with($path, self::TEST_PREFIX . '/') || str_contains($path, '..')) {
            abort(403);
        }

        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        return redirect()
            ->route('storage-test.index')
            ->with('status', __('messages.storage_test.deleted'));
    }

    /**
     * @return array{path: string, name: string, url: string, size: int, mime: string|null, last_modified: int, is_image: bool}
     */
    private function filePayload(string $diskName, string $path): array
    {
        $disk = Storage::disk($diskName);
        $mime = $disk->mimeType($path);

        return [
            'path' => $path,
            'name' => basename($path),
            'url' => $disk->url($path),
            'size' => $disk->size($path),
            'mime' => $mime,
            'last_modified' => $disk->lastModified($path),
            'is_image' => is_string($mime) && str_starts_with($mime, 'image/'),
        ];
    }
}
