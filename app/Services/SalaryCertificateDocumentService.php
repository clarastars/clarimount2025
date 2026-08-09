<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SalaryCertificateRequest;
use Illuminate\Support\Facades\Storage;
use TCPDF;

class SalaryCertificateDocumentService
{
    /**
     * @return array{disk: string, path: string, name: string}
     */
    public function storeGeneratedPdf(SalaryCertificateRequest $certificateRequest): array
    {
        $binary = $this->renderPdf($certificateRequest);
        $diskName = (string) config('filesystems.cloud', 's3');
        $filename = sprintf(
            'salary-certificate-%d-%s.pdf',
            $certificateRequest->id,
            now()->format('YmdHis'),
        );
        $path = 'salary-certificates/'.$certificateRequest->employee_id.'/'.$filename;

        Storage::disk($diskName)->put($path, $binary, [
            'visibility' => 'private',
            'ContentType' => 'application/pdf',
        ]);

        return [
            'disk' => $diskName,
            'path' => $path,
            'name' => $filename,
        ];
    }

    public function previewHtml(SalaryCertificateRequest $certificateRequest): string
    {
        return view('documents.salary-certificate', $this->buildViewData($certificateRequest, forPdf: false))->render();
    }

    public function renderPdf(SalaryCertificateRequest $certificateRequest): string
    {
        $data = $this->buildViewData($certificateRequest, forPdf: true);
        $html = view('documents.partials.salary-certificate-body', $data)->render();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('HR System');
        $pdf->SetAuthor((string) $data['companyName']);
        $pdf->SetTitle('تعريف بالراتب — '.$data['employeeName']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(16, 16, 16);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->setRTL(true);
        $pdf->SetFont('aealarabiya', '', 12);
        $pdf->AddPage();

        $styledHtml = <<<HTML
<style>
    .sheet { font-family: aealarabiya; font-size: 12pt; color: #1f2937; }
    .logo-wrap { text-align: center; margin-bottom: 10px; }
    .greeting { text-align: center; font-family: aealarabiya; font-size: 15pt; margin: 12px 0 16px; }
    .intro, .closing { text-align: right; font-family: aealarabiya; font-size: 12pt; line-height: 1.9; margin: 0 0 14px; }
    .value { font-family: aealarabiya; text-decoration: underline; }
    .thanks { text-align: center; font-family: aealarabiya; font-size: 14pt; margin-top: 22px; }
    table.salary-table { border-collapse: collapse; font-family: aealarabiya; }
    table.salary-table th, table.salary-table td { border: 1px solid #4b5563; padding: 7px 10px; font-size: 11pt; font-family: aealarabiya; }
</style>
{$html}
HTML;

        $pdf->writeHTML($styledHtml, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(SalaryCertificateRequest $certificateRequest, bool $forPdf = false): array
    {
        $certificateRequest->loadMissing(['employee.company', 'employee.nationality']);

        $employee = $certificateRequest->employee;
        $company = $employee->company;

        $basic = (float) ($employee->basic_salary ?? 0);
        $housing = (float) ($employee->allowance_housing ?? 0);
        $transport = (float) ($employee->allowance_transportation ?? 0);

        $companyName = trim((string) ($company?->name_ar ?: $company?->name_en ?: ''));
        if ($companyName !== '' && ! str_starts_with($companyName, 'شركة')) {
            $companyName = 'شركة '.$companyName;
        }

        return [
            'forPdf' => $forPdf,
            'companyName' => $companyName !== '' ? $companyName : '—',
            'logoSrc' => $this->resolveLogoSrc($company?->logo, $forPdf),
            'employeeName' => trim((string) $employee->full_name) !== '' ? $employee->full_name : '—',
            'nationality' => $employee->nationality?->name_ar ?: $employee->nationality?->name_en ?: '—',
            'idNumber' => filled($employee->id_number) ? (string) $employee->id_number : '—',
            'jobTitle' => filled($employee->job_title) ? (string) $employee->job_title : '—',
            'hireDate' => $employee->hire_date?->format('d / m / Y') ?? '— / — / —',
            'basicSalary' => $this->formatRiyal($basic),
            'housingAllowance' => $this->formatRiyal($housing),
            'transportAllowance' => $this->formatRiyal($transport),
            'totalSalary' => $this->formatRiyal($basic + $housing + $transport),
        ];
    }

    private function resolveLogoSrc(?string $logo, bool $forPdf): ?string
    {
        if (! filled($logo)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($logo);
        if (! is_file($absolutePath)) {
            return null;
        }

        if ($forPdf) {
            return str_replace('\\', '/', $absolutePath);
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';
        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function formatRiyal(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', ',');
        if (str_ends_with($formatted, '.00')) {
            $formatted = substr($formatted, 0, -3);
        }

        return '('.$formatted.') ريالاً فقط لا غير';
    }
}
