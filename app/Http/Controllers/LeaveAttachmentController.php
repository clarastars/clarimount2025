<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LeaveAttachmentService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveAttachmentController extends Controller
{
    public function show(string $filename): StreamedResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $filename) === 1, 404);

        $path = LeaveAttachmentService::DIRECTORY.'/'.$filename;
        $disk = Storage::disk(LeaveAttachmentService::DISK);

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $filename, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
