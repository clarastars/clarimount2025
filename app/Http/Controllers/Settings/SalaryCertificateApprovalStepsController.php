<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\ManagesSalaryCertificateApprovalSteps;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SalaryCertificateApprovalStepsController extends Controller
{
    use ManagesSalaryCertificateApprovalSteps;

    public function index(): Response
    {
        $user = Auth::user();

        abort_unless(
            $user->hasRole('super-admin') || $user->can('settings.access'),
            403
        );

        $companies = $this->manageableCompaniesForSalaryCertificateApprovalSteps()
            ->map(fn ($company) => [
                'id' => $company->id,
                'name_en' => $company->name_en,
                'name_ar' => $company->name_ar,
            ])
            ->values();

        return Inertia::render('settings/SalaryCertificateApprovalCompanyPicker', [
            'companies' => $companies,
        ]);
    }
}
