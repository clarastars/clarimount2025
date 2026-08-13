<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\SalaryCertificateFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryCertificateFeeSettingsController extends Controller
{
    public function __construct(
        private SalaryCertificateFeeService $feeService,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('settings/SalaryCertificateFee', [
            'settings' => [
                'chamber_fee' => $this->feeService->chamberFee(),
            ],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'chamber_fee' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $this->feeService->setChamberFee((float) $validated['chamber_fee']);

        return redirect()
            ->route('settings.salary-certificate-fee.edit')
            ->with('status', __('messages.settings.salary_certificate_fee_saved'));
    }
}
