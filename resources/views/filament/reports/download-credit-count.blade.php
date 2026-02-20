<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; padding: 28px 36px; }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .header-table td { border: none; padding: 0; vertical-align: middle; }
        .header-logo-cell { width: 75px; text-align: left; }
        .header-text-cell { text-align: center; }
        .header-text-cell h1 { font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .header-text-cell h2 { font-size: 12px; margin-top: 4px; }
        .header-text-cell p  { font-size: 10px; color: #555; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #bbb; padding: 5px 7px; vertical-align: top; }
        th { background-color: #1e3a5f; color: #fff; font-size: 10px; text-align: center; }

        td.num-cell  { text-align: center; color: #888; width: 28px; }
        td.name-cell { font-weight: bold; white-space: nowrap; }
        td.course-cell { font-size: 9px; }
        td.credit-cell { text-align: center; font-weight: bold; color: #1e3a5f; white-space: nowrap; }
        .course-row { padding: 1px 0; }
        .course-code { font-weight: bold; color: #1e3a5f; }

        tfoot td { background-color: #f3f4f6; font-weight: bold; font-size: 10px; }
        tfoot td.footer-total { text-align: center; color: #1e3a5f; }

        .sig-block { margin-top: 28px; text-align: center; }
        .sig-line { border-top: 1px solid #333; width: 200px; margin: 0 auto;
                    padding-top: 4px; font-size: 9px; line-height: 1.5; }

        .footer { margin-top: 14px; text-align: right; font-size: 8px; color: #888; }
    </style>
</head>
<body>

    @php
        $logoB64 = null;
        if ($setting->logoAbsPath() && file_exists($setting->logoAbsPath())) {
            $logoB64 = 'data:'.mime_content_type($setting->logoAbsPath()).';base64,'.base64_encode(file_get_contents($setting->logoAbsPath()));
        }
        $sigB64 = null;
        if ($setting->signatureAbsPath() && file_exists($setting->signatureAbsPath())) {
            $sigB64 = 'data:'.mime_content_type($setting->signatureAbsPath()).';base64,'.base64_encode(file_get_contents($setting->signatureAbsPath()));
        }
        $grandTotal = collect($results)->sum('total_credits');
    @endphp

    <table class="header-table">
        <tr>
            <td class="header-logo-cell">
                @if($logoB64)
                    <img src="{{ $logoB64 }}" style="height:60px;" alt="Logo">
                @endif
            </td>
            <td class="header-text-cell">
                <h1>{{ $setting->institution_name }}</h1>
                <h2>Teacher Credit Count Report</h2>
                <p>{{ $departmentName }} &mdash; {{ $semesterLabel }}</p>
            </td>
            @if($logoB64)
                <td style="width:75px;"></td>
            @endif
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width:28px;">#</th>
                <th style="width:140px; text-align:left;">Teacher</th>
                <th style="text-align:left;">Courses</th>
                <th style="width:70px;">Total Credits</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $i => $row)
                <tr>
                    <td class="num-cell">{{ $i + 1 }}</td>
                    <td class="name-cell">
                        {{ $row['teacher_name'] }}
                        @if($row['teacher_short'] !== $row['teacher_name'])
                            <br><span style="font-size:8px; color:#666; font-weight:normal;">({{ $row['teacher_short'] }})</span>
                        @endif
                    </td>
                    <td class="course-cell">
                        @foreach($row['courses'] as $course)
                            <div class="course-row">
                                <span class="course-code">{{ $course['code'] }}</span>
                                &mdash; {{ $course['name'] }}
                                <span style="color:#888;">({{ $course['credit_hours'] }} cr)</span>
                            </div>
                        @endforeach
                    </td>
                    <td class="credit-cell">{{ number_format($row['total_credits'], 1) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right; padding-right:10px;">Grand Total</td>
                <td class="footer-total">{{ number_format($grandTotal, 1) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="sig-block">
        @if($sigB64)
            <img src="{{ $sigB64 }}" style="height:48px; display:block; margin:0 auto 4px;" alt="Signature">
        @endif
        <div class="sig-line">
            {{ $setting->principal_name ?? 'Principal' }}<br>
            @if($setting->principal_title){{ $setting->principal_title }}<br>@endif
            {{ $setting->institution_name }}
        </div>
    </div>

    <div class="footer">Generated: {{ now()->format('d M Y, h:i A') }}</div>

</body>
</html>
