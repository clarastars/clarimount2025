<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعريف بالراتب — {{ $employeeName }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        * { box-sizing: border-box; }
        body {
            font-family: Tahoma, "Simplified Arabic", Arial, sans-serif;
            direction: rtl;
            text-align: right;
            color: #1f2937;
            margin: 0;
            padding: {{ !empty($forPdf) ? '0' : '24px' }};
            background: {{ !empty($forPdf) ? '#ffffff' : '#e5e7eb' }};
            font-size: 14px;
            line-height: 1.9;
        }
        .sheet {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: {{ !empty($forPdf) ? '8px 6px 20px' : '28px 36px 40px' }};
            min-height: {{ !empty($forPdf) ? 'auto' : '1122px' }};
        }
        .logo-wrap { text-align: center; margin-bottom: 16px; }
        .logo-wrap img { max-height: 90px; max-width: 220px; }
        .greeting { text-align: center; font-size: 18px; font-weight: 700; margin: 16px 0 22px; }
        .addressee { text-align: right; margin: 0 0 16px; font-size: 15px; }
        .intro { text-align: right; margin: 0 0 18px; font-size: 15px; }
        .value { font-weight: bold; text-decoration: underline; }
        table.salary-table {
            width: 92%;
            margin: 8px auto 22px;
            border-collapse: collapse;
        }
        table.salary-table th,
        table.salary-table td {
            border: 1px solid #4b5563;
            padding: 8px 12px;
            font-size: 14px;
        }
        table.salary-table th {
            width: 34%;
            background-color: #d1d5db;
            font-weight: bold;
            text-align: center;
        }
        table.salary-table td {
            width: 66%;
            text-align: center;
            background-color: #ffffff;
        }
        table.salary-table tr.total th,
        table.salary-table tr.total td {
            font-weight: bold;
            background-color: #e5e7eb;
        }
        .thanks { text-align: center; font-size: 16px; font-weight: bold; margin-top: 28px; }
        @media print {
            body { background: #ffffff; padding: 0; }
            .sheet { box-shadow: none; max-width: none; padding: 0; min-height: 0; }
        }
    </style>
</head>
<body>
    @include('documents.partials.salary-certificate-body')
</body>
</html>
