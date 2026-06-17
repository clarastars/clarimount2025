<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeDocumentService
{
    public function diskName(): string
    {
        return (string) config('filesystems.cloud', 's3');
    }

    public function store(Employee $employee, string $type, UploadedFile $file, ?int $uploadedBy): EmployeeDocument
    {
        if (! in_array($type, EmployeeDocument::types(), true)) {
            throw new \InvalidArgumentException('Invalid employee document type.');
        }

        $diskName = $this->diskName();
        $disk = Storage::disk($diskName);

        $existing = EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('type', $type)
            ->first();

        if ($existing) {
            if ($disk->exists($existing->path)) {
                $disk->delete($existing->path);
            }
            $existing->delete();
        }

        $extension = $file->getClientOriginalExtension();
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $filename = $safeName . '-' . now()->format('YmdHis') . ($extension !== '' ? '.' . $extension : '');
        $path = $file->storeAs(
            'employees/' . $employee->id . '/' . $type,
            $filename,
            [
                'disk' => $diskName,
                'visibility' => config('filesystems.disks.' . $diskName . '.visibility', 'public'),
            ],
        );

        return EmployeeDocument::query()->create([
            'employee_id' => $employee->id,
            'type' => $type,
            'disk' => $diskName,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function delete(EmployeeDocument $document): void
    {
        $disk = Storage::disk($document->disk);

        if ($disk->exists($document->path)) {
            $disk->delete($document->path);
        }

        $document->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function toUiArray(EmployeeDocument $document): array
    {
        $mime = $document->mime_type;

        return [
            'type' => $document->type,
            'url' => Storage::disk($document->disk)->url($document->path),
            'name' => $document->original_name,
            'mime_type' => $mime,
            'size' => $document->size,
            'is_image' => is_string($mime) && str_starts_with($mime, 'image/'),
            'uploaded_at' => $document->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documentsForEmployee(Employee $employee): array
    {
        return $employee->documents
            ->map(fn (EmployeeDocument $document) => $this->toUiArray($document))
            ->values()
            ->all();
    }
}
