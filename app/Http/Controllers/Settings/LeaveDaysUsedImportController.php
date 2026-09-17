<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Exports\LeaveDaysUsedImportSampleExport;
use App\Http\Controllers\Controller;
use App\Models\LeaveDaysUsedImport;
use App\Services\LeaveDaysUsedImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeaveDaysUsedImportController extends Controller
{
    public function index(Request $request, LeaveDaysUsedImportService $service): Response
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        return Inertia::render('settings/LeaveDaysUsedImport', [
            'lastResult' => $request->session()->get('leave_days_used_import_result'),
            'undoResult' => $request->session()->get('leave_days_used_import_undo_result'),
            'recentImports' => $service->recentImports(),
        ]);
    }

    public function sample(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $filename = 'leave-days-used-import-sample.xlsx';

        return Excel::download(
            new LeaveDaysUsedImportSampleExport,
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function store(Request $request, LeaveDaysUsedImportService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        try {
            $result = $service->import($validated['file'], $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors([
                'file' => $exception->getMessage(),
            ]);
        }

        $flashKey = $result['skipped'] > 0 ? 'warning' : 'success';
        $flashMessage = $result['skipped'] > 0
            ? __('messages.settings.leave_days_used_import_partial', [
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
            ])
            : __('messages.settings.leave_days_used_import_success', [
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
            ]);

        return redirect()
            ->route('settings.leave-days-used-import.index')
            ->with('leave_days_used_import_result', $result)
            ->with($flashKey, $flashMessage);
    }

    public function undo(Request $request, LeaveDaysUsedImport $import, LeaveDaysUsedImportService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        try {
            $result = $service->undo($import, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('settings.leave-days-used-import.index')
            ->with('leave_days_used_import_undo_result', $result)
            ->with('success', __('messages.settings.leave_days_used_import_undo_success', [
                'restored' => $result['restored'],
                'adjusted' => $result['adjusted'],
                'failed' => $result['failed'],
            ]));
    }
}
