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

        table.main { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        table.main tr { page-break-inside: avoid; page-break-after: auto; }
        table.main thead { display: table-header-group; }
        table.main th, table.main td { border: 1px solid #bbb; padding: 6px 8px; vertical-align: top; }
        table.main th { background-color: #1e3a5f; color: #fff; font-size: 10px; text-align: center; }

        td.num-cell  { text-align: center; color: #888; width: 28px; }
        td.name-cell { font-weight: bold; }
        td.duty-cell { text-align: center; font-weight: bold; color: #1e3a5f; font-size: 11px; }

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
        $totalDuties = collect($results)->sum('duties');
        $roleLabel = $reportType === 'invigilator' ? 'Invigilation' : 'Supervision';
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
                <h2>Exam {{ $roleLabel }} Duty Report</h2>
                <p>{{ $examName }} &mdash; {{ $examYear }}</p>
            </td>
            @if($logoB64)
                <td style="width:75px;"></td>
            @endif
        </tr>
    </table>

    <table class="main">
        <thead>
            <tr>
                <th style="width:28px;">#</th>
                <th style="text-align:left;">Name</th>
                <th style="width:120px;">Number of {{ $roleLabel }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $i => $row)
                <tr>
                    <td class="num-cell">{{ $i + 1 }}</td>
                    <td class="name-cell">{{ $row['name'] }}</td>
                    <td class="duty-cell">{{ $row['duties'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="text-align:right; padding-right:10px;">Total Duties</td>
                <td class="footer-total">{{ $totalDuties }}</td>
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
