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
        th, td { border: 1px solid #bbb; padding: 5px 6px; text-align: center; vertical-align: middle; }
        th { background-color: #1e3a5f; color: #fff; font-size: 9px; }

        td.day-cell { background-color: #f3f4f6; font-weight: bold; font-size: 9px; text-align: left; width: 70px; white-space: nowrap; }
        td.assigned     { background-color: #eff6ff; }
        td.assigned-lab { background-color: #fffbeb; }
        td.empty { color: #ccc; }
        th.break-header { background-color: #6b7280; }
        td.break-cell { background-color: #f9fafb; color: #9ca3af; text-align: center; font-size: 8px; }
        .course-code { font-weight: bold; font-size: 10px; }
        .course-name { font-size: 8px; color: #444; }
        .teacher     { font-size: 9px; color: #1e3a5f; font-weight: bold; }

        .legend { margin-top: 18px; }
        .legend-title { font-size: 9px; font-weight: bold; color: #333; text-transform: uppercase;
                        letter-spacing: 0.5px; margin-bottom: 6px; border-bottom: 1px solid #ddd;
                        padding-bottom: 3px; }
        .legend table { border: none; }
        .legend td { border: none; padding: 2px 10px 2px 0; font-size: 9px; text-align: left; vertical-align: top; }
        .legend td.abbr { font-weight: bold; color: #1e3a5f; white-space: nowrap; }

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

        // Collect unique teachers from all assignments, keyed by short name
        $teacherLegend = collect();
        foreach ($assignments as $daySlots) {
            foreach ($daySlots as $a) {
                $teacherLegend->put($a['teacher_short'], $a['teacher_name']);
            }
        }
        $teacherLegend = $teacherLegend->sortKeys();
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
                <h2>Class Routine &mdash; {{ $batch->department->name }}</h2>
                <p>Batch {{ $batch->batch_number }} &nbsp;|&nbsp; Session: {{ $batch->session }} &nbsp;|&nbsp; Semester {{ $batch->current_semester }}</p>
            </td>
            {{-- spacer cell to balance logo side --}}
            @if($logoB64)
                <td style="width:75px;"></td>
            @endif
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width:70px;">Day</th>
                @foreach ($timeSlots as $slot)
                    @if ($slot->type->value === 'break')
                        <th class="break-header">
                            Break<br>
                            <span style="font-weight:normal; font-size:8px;">
                                {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}<br>
                                {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                            </span>
                        </th>
                    @else
                        <th>
                            {{ $slot->name }}<br>
                            <span style="font-weight:normal; font-size:8px;">
                                {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}<br>
                                {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                            </span>
                        </th>
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($days as $dayIndex => $dayName)
                <tr>
                    <td class="day-cell">{{ $dayName }}</td>
                    @foreach ($timeSlots as $slot)
                        @if ($slot->type->value === 'break')
                            <td class="break-cell">Break</td>
                        @else
                            @php $a = $assignments[$dayIndex][$slot->id] ?? null @endphp
                            @if ($a)
                                <td class="{{ str_contains($a['course_type'], 'Lab') ? 'assigned-lab' : 'assigned' }}">
                                    <div class="course-code">{{ $a['course_code'] }}</div>
                                    <div class="course-name">{{ $a['course_name'] }}</div>
                                    <div class="teacher">{{ $a['teacher_short'] }}</div>
                                </td>
                            @else
                                <td class="empty">&mdash;</td>
                            @endif
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($teacherLegend->isNotEmpty())
        <div class="legend">
            <div class="legend-title">Teacher Reference</div>
            <table>
                <tr>
                    @php $chunks = $teacherLegend->chunk(4) @endphp
                    @foreach($chunks as $chunk)
                        <td style="vertical-align:top; padding-right:24px; border:none;">
                            <table>
                                @foreach($chunk as $short => $full)
                                    <tr>
                                        <td class="abbr">{{ $short }}</td>
                                        <td style="color:#333;">{{ $full }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

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
