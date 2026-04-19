<!DOCTYPE html>
<html>
<head>
    <title>Asset Custody Change Form</title>
    <meta charset="utf-8">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            color: #333;
            line-height: 1.4;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .section { 
            margin: 20px 0; 
            page-break-inside: avoid;
        }
        .table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0;
        }
        .table th, .table td { 
            border: 1px solid #ddd; 
            padding: 8px; 
            text-align: left; 
            font-size: 12px;
        }
        .table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .signature-section { 
            margin-top: 50px; 
            page-break-inside: avoid;
        }
        .signature-box {
            border: 1px solid #333;
            height: 80px;
            margin: 10px 0;
            position: relative;
        }
        .signature-label {
            position: absolute;
            bottom: 5px;
            left: 10px;
            font-size: 10px;
            color: #666;
        }
        .employee-info {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
        }
        .employee-info > div {
            flex: 1;
            margin-right: 20px;
        }
        .employee-info > div:last-child {
            margin-right: 0;
        }
        .info-item {
            margin: 5px 0;
            font-size: 12px;
        }
        .info-label {
            font-weight: bold;
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }
        .no-assets {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }
        .changes-summary {
            background-color: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #3b82f6;
            margin: 15px 0;
        }
        @media print {
            body { margin: 0; }
            .signature-section { page-break-before: auto; }
        }
    </style>
</head>
<body>
    <div class="company-info">
        <h2>مجموعة فهد نواف الزير التجارة</h2>
        <p>info@alzeer-group.com</p>
    </div>

    <div class="header">
        <h1>نموذج استلام عهدة</h1>
        <p><strong>Document ID:</strong> {{ $custodyChange->id }}</p>
        <p><strong>Date:</strong> {{ $custodyChange->created_at->format('Y-m-d H:i:s') }}</p>
        <div class="status-badge status-{{ $custodyChange->status }}">
            {{ strtoupper($custodyChange->status) }}
        </div>
    </div>
    
    <div class="employee-info">
        <div>
            <div class="info-item">
                <span class="info-label">Employee Name:</span> {{ $employee->full_name }}
            </div>
            <div class="info-item">
                <span class="info-label">Employee ID:</span> {{ $employee->employee_id }}
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span> {{ $employee->work_email ?: $employee->personal_email }}
            </div>
        </div>
        <div>
            <div class="info-item">
                <span class="info-label">Department:</span> {{ $employee->department ?? 'N/A' }}
            </div>
            <div class="info-item">
                <span class="info-label">Job Title:</span> {{ $employee->job_title ?? 'N/A' }}
            </div>
            <div class="info-item">
                <span class="info-label">Updated By:</span> {{ $custodyChange->updatedBy->name ?? 'System' }}
            </div>
        </div>
    </div>

    @if($custodyChange->changes_summary)
    <div class="changes-summary">
        <strong>Change Summary:</strong> {{ $custodyChange->changes_summary }}
    </div>
    @endif
    
    <div class="section">
        <h2>Previous Custody ({{ count($previousAssets) }} items)</h2>
        @if(count($previousAssets) > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Description</th>
                        <th>Model</th>
                        <th>Serial Number</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Condition</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($previousAssets as $asset)
                    <tr>
                        <td>{{ $asset['asset_tag'] }}</td>
                        <td>{{ $asset['model_name'] ?? $asset['asset_tag'] }}</td>
                        <td>{{ $asset['model_number'] ?? 'N/A' }}</td>
                        <td>{{ $asset['serial_number'] ?? 'N/A' }}</td>
                        <td>{{ $asset['category_name'] ?? 'N/A' }}</td>
                        <td>{{ $asset['location_name'] ?? 'N/A' }}</td>
                        <td>{{ ucfirst($asset['condition'] ?? 'N/A') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-assets">No assets were previously assigned</div>
        @endif
    </div>
    
    <div class="section">
        <h2>New Custody ({{ count($newAssets) }} items)</h2>
        @if(count($newAssets) > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Description</th>
                        <th>Model</th>
                        <th>Serial Number</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Condition</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($newAssets as $asset)
                    <tr>
                        <td>{{ $asset['asset_tag'] }}</td>
                        <td>{{ $asset['model_name'] ?? $asset['asset_tag'] }}</td>
                        <td>{{ $asset['model_number'] ?? 'N/A' }}</td>
                        <td>{{ $asset['serial_number'] ?? 'N/A' }}</td>
                        <td>{{ $asset['category_name'] ?? 'N/A' }}</td>
                        <td>{{ $asset['location_name'] ?? 'N/A' }}</td>
                        <td>{{ ucfirst($asset['condition'] ?? 'N/A') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-assets">No assets assigned in new custody</div>
        @endif
    </div>

    <div class="section">
        <h3>Terms and Conditions</h3>
        <ul style="font-size: 12px; line-height: 1.6;">
            <li>I acknowledge receipt of the assets listed above and accept responsibility for their care and proper use.</li>
            <li>I understand that I am accountable for any loss, damage, or theft of company assets in my custody.</li>
            <li>I agree to return all assets in good condition upon request or when my employment ends.</li>
            <li>I will immediately report any loss, damage, or theft of company assets to my supervisor.</li>
            <li>I understand that failure to return company assets may result in deductions from my final pay.</li>
        </ul>
    </div>
    
    <div class="signature-section">
        <h3>Signatures</h3>
        <div style="display: flex; justify-content: space-between; margin-top: 30px;">
            <div style="width: 45%;">
                <div class="signature-box">
                    <div class="signature-label">Employee Signature</div>
                </div>
                <div style="margin-top: 10px; text-align: center;">
                    <strong>{{ $employee->full_name }}</strong><br>
                    <small>{{ $employee->job_title ?? 'Employee' }}</small><br>
                    <small>Date: _______________________</small>
                </div>
            </div>
            <div style="width: 45%;">
                <div class="signature-box">
                    <div class="signature-label">Admin/Manager Signature</div>
                </div>
                <div style="margin-top: 10px; text-align: center;">
                    <strong>{{ $custodyChange->updatedBy->name ?? '____________________' }}</strong><br>
                    <small>IT Administrator</small><br>
                    <small>Date: _______________________</small>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; font-size: 10px; color: #666; text-align: center;">
        <p>This document was generated automatically on {{ now()->format('Y-m-d H:i:s') }}</p>
        <p>Document ID: {{ $custodyChange->id }} | Status: {{ $custodyChange->status }}</p>
    </div>
</body>
</html> 