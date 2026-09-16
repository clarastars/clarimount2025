<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEmployeeAccess;
use App\Models\Employee;
use App\Models\EmployeeEntitlementSettlement;
use App\Services\EntitlementSettlementAttachmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntitlementSettlementAttachmentController extends Controller
{
    use AuthorizesEmployeeAccess;

    public function __construct(
        private EntitlementSettlementAttachmentService $attachmentService,
    ) {}

    public function show(
        Employee $employee,
        EmployeeEntitlementSettlement $entitlementSettlement,
        string $filename,
    ): StreamedResponse {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless((int) $entitlementSettlement->employee_id === (int) $employee->id, 404);

        $this->abortUnlessCanViewEntitlementSettlement($user, $employee, $entitlementSettlement);

        $ownedPaths = $this->attachmentService->normalizeStoredPaths($entitlementSettlement->attachment_paths);
        $path = $this->attachmentService->resolveOwnedPath($ownedPaths, $filename);
        abort_unless($path !== null, 404);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($this->attachmentService->diskName());

        return $disk->response($path, $filename, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
