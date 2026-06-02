<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 9pt; color: #000; }
        .header { text-align: center; margin-bottom: 8px; }
        .header h2 { font-size: 13pt; font-weight: bold; }
        .header h3 { font-size: 10pt; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 3px 4px; text-align: center; vertical-align: middle; font-size: 8pt; }
        .th-day { background: #1a237e; color: #fff; font-weight: bold; font-size: 9pt; }
        .th-period { background: #e3f2fd; color: #000; font-size: 7.5pt; }
        .th-label { background: #1a237e; color: #fff; text-align: left; }
        .dept-cell { background: #e8f0fe; color: #1a237e; font-weight: bold; text-align: left; padding-left: 5px; }
        .sem-cell { background: #fff9c4; color: #b71c1c; font-weight: bold; }
        .lab-cell { background: #fff3e0; }
        .even-row { background: #fafafa; }
        .course-code { font-weight: bold; font-size: 8pt; }
        .teacher { color: #37474f; font-size: 7.5pt; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Master Routine: {{ now()->year }}</h2>
        <h3>{{ $setting->institution_name }}</h3>
    </div>

    <table>
        <thead>
            <tr>
                <th class="th-label" rowspan="2" style="width: 90px;">Department</th>
                <th class="th-label" rowspan="2" style="width: 28px;">Sem</th>
                @foreach($days as $dayIndex => $dayName)
                    <th class="th-day" colspan="{{ count($timeSlots) }}">{{ $dayName }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($days as $dayIndex => $dayName)
                    @foreach($timeSlots as $ts)
                        <th class="th-period">
                            <div>{{ $ts->name }}</div>
                            <div style="font-size: 6.5pt; font-weight: normal;">
                                {{ \Carbon\Carbon::parse($ts->start_time)->format('H:i') }}
                            </div>
                        </th>
                    @endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php $prevDept = null; $rowIndex = 0; @endphp
            @foreach($reportRows as $key => $row)
                <tr class="{{ $rowIndex % 2 === 0 ? 'even-row' : '' }}">
                    @if($row['dept_name'] !== $prevDept)
                        @php
                            $deptRowCount = collect($reportRows)->where('dept_name', $row['dept_name'])->count();
                            $prevDept = $row['dept_name'];
                        @endphp
                        <td class="dept-cell" rowspan="{{ $deptRowCount }}" style="vertical-align: middle;">
                            {{ $row['dept_code'] }}
                        </td>
                    @endif
                    <td class="sem-cell">{{ $row['semester'] }}</td>
                    @foreach($days as $dayIndex => $dayName)
                        @php $skipCount = 0; @endphp
                        @foreach($timeSlots as $ts)
                            @if($skipCount > 0)
                                @php $skipCount--; @endphp
                                @continue
                            @endif
                            @php
                                $cell = $row['slots'][$dayIndex][$ts->id] ?? null;
                                $span = $cell ? ($cell['lab_span'] ?? 1) : 1;
                                if ($span > 1) $skipCount = $span - 1;
                            @endphp
                            <td colspan="{{ $span }}" class="{{ $span > 1 ? 'lab-cell' : '' }}">
                                @if($cell)
                                    <div class="course-code">{{ $cell['course_code'] }}</div>
                                    <div class="teacher">{{ $cell['teacher_short'] }}</div>
                                @endif
                            </td>
                        @endforeach
                    @endforeach
                </tr>
                @php $rowIndex++; @endphp
            @endforeach
        </tbody>
    </table>
</body>
</html>
