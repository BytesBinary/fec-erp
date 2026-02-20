<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; padding: 28px 36px; }
        .header { text-align: center; margin-bottom: 14px; }
        .header h1 { font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .header p { font-size: 9px; color: #555; margin-top: 2px; }
        .subtitle { text-align: center; font-size: 12px; font-weight: bold; margin-bottom: 6px; }
        .time-row { text-align: center; font-size: 9px; color: #555; margin-bottom: 12px; }

        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }

        th, td {
            border: 1px solid #bbb;
            padding: 6px 8px;
            text-align: center;
            vertical-align: top;
        }
        th { background-color: #1e3a5f; color: #fff; font-size: 10px; }

        td.date-cell {
            font-weight: bold;
            background-color: #f3f4f6;
            white-space: nowrap;
            vertical-align: middle;
        }
        td.sig-cell { width: 80px; min-height: 36px; }
        .item { padding: 2px 0; border-bottom: 1px dotted #ddd; }
        .item:last-child { border-bottom: none; }

        .footer { margin-top: 16px; text-align: right; font-size: 8px; color: #888; }
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
    @endphp

    <div class="header">
        @if($logoB64)
            <img src="{{ $logoB64 }}" style="height:60px; display:block; margin:0 auto 8px;" alt="Logo">
        @endif
        <h1>{{ $setting->institution_name }}</h1>
        @if($setting->address)
            <p>{{ $setting->address }}</p>
        @endif
    </div>

    <div class="subtitle">Exam Invigilator Duty Sheet &mdash; {{ $examDuty['exam_name'] }} &mdash; {{ $examDuty['exam_year'] }}</div>
    <div class="time-row">Time: {{ $examDuty['start_time'] }} &ndash; {{ $examDuty['end_time'] }}</div>

    <table>
        <thead>
            <tr>
                <th style="width:70px;">Date</th>
                <th>Invigilator Name</th>
                <th style="width:70px;">Signature</th>
                <th>Course</th>
                <th>Exam Hall</th>
                <th>Supervisor</th>
                <th style="width:70px;">Supervisor<br>Signature</th>
            </tr>
        </thead>
        <tbody>
            @forelse($examDuty['duty_details'] as $detail)
                {{-- One row per date; all values stacked inside cells, no rowspan --}}
                <tr>
                    <td class="date-cell">{{ $detail['date'] }}</td>

                    <td>
                        @foreach($detail['invigilator'] as $name)
                            <div class="item">{{ $name }}</div>
                        @endforeach
                    </td>

                    {{-- Signature column: one blank line per invigilator --}}
                    <td class="sig-cell">
                        @foreach($detail['invigilator'] as $name)
                            <div class="item" style="min-height:18px;">&nbsp;</div>
                        @endforeach
                    </td>

                    <td>
                        @foreach($detail['course'] as $course)
                            <div class="item">{{ $course }}</div>
                        @endforeach
                    </td>

                    <td>
                        @foreach($detail['exam_hall'] as $hall)
                            <div class="item">{{ $hall }}</div>
                        @endforeach
                    </td>

                    <td>
                        @foreach($detail['supervisor'] as $name)
                            <div class="item">{{ $name }}</div>
                        @endforeach
                    </td>

                    {{-- Supervisor signature column: one blank line per supervisor --}}
                    <td class="sig-cell">
                        @foreach($detail['supervisor'] as $name)
                            <div class="item" style="min-height:18px;">&nbsp;</div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center; color:#999; padding:12px;">No duty details found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:30px; text-align:center;">
        @if($sigB64)
            <img src="{{ $sigB64 }}" style="height:48px; display:block; margin:0 auto 4px;" alt="Signature">
        @endif
        <div style="border-top:1px solid #333; width:200px; margin:0 auto; padding-top:4px; font-size:9px;">
            {{ $setting->principal_name ?? 'Principal' }}<br>
            @if($setting->principal_title){{ $setting->principal_title }}<br>@endif
            {{ $setting->institution_name }}
        </div>
    </div>

    <div class="footer">Generated: {{ now()->format('d M Y, h:i A') }}</div>
</body>
</html>
