<div class="sheet">
    @if(!empty($logoSrc))
        <div class="logo-wrap">
            <img src="{{ $logoSrc }}" alt="{{ $companyName }}" width="180">
        </div>
    @endif

    <p class="greeting">السلام عليكم ورحمة الله وبركاته؛؛؛</p>

    <p class="intro">
        نفيدكم نحن
        <span class="value">{{ $companyName }}</span>
        أن السيد/
        <span class="value">{{ $employeeName }}</span>
        الجنسية
        <span class="value">{{ $nationality }}</span>
        بموجب هوية مقيم رقم
        <span class="value">{{ $idNumber }}</span>
        ، يعمل لدينا ومازال على رأس العمل حتى تاريخه بالمميزات التالية :
    </p>

    <table class="salary-table" cellpadding="6" cellspacing="0" border="1" width="92%" align="center">
        <tr>
            <th width="34%" bgcolor="#d1d5db" align="center">المسمى الوظيفي</th>
            <td width="66%" align="center">{{ $jobTitle }}</td>
        </tr>
        <tr>
            <th bgcolor="#d1d5db" align="center">تاريخ التعيين</th>
            <td align="center">{{ $hireDate }} م</td>
        </tr>
        <tr>
            <th bgcolor="#d1d5db" align="center">الراتب الأساسي</th>
            <td align="center">{{ $basicSalary }}</td>
        </tr>
        <tr>
            <th bgcolor="#d1d5db" align="center">بدل السكن</th>
            <td align="center">{{ $housingAllowance }}</td>
        </tr>
        <tr>
            <th bgcolor="#d1d5db" align="center">بدل النقل</th>
            <td align="center">{{ $transportAllowance }}</td>
        </tr>
        <tr>
            <th bgcolor="#e5e7eb" align="center">الإجمالي</th>
            <td bgcolor="#e5e7eb" align="center">{{ $totalSalary }}</td>
        </tr>
    </table>

    <p class="thanks">شاكرين لكم حسن تعاونكم معنا؛؛؛</p>
</div>
