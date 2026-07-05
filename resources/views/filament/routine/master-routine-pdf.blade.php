<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial Narrow', 'Arial', sans-serif; font-size: 7pt; color: #000; padding: 0; }
        .header { text-align: center; margin-bottom: 4px; }
        .header h2 { font-size: 11pt; font-weight: bold; }
        .header h3 { font-size: 8pt; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #aaa; padding: 3px 5px; text-align: center; vertical-align: middle; font-size: 6.5pt; word-break: break-word; overflow: hidden; }

        /* Header label cells */
        .lbl { background: #ffff00; color: #000; font-weight: bold; font-size: 6.5pt; }

        /* Day name headers */
        .th-day { background: #1a237e; color: #fff; font-weight: bold; font-size: 7pt; border-right: 2px solid #777; }

        /* Period number cells */
        .th-period-odd  { background: #ffff00; color: #000; font-weight: bold; }
        .th-period-even { background: #ffa500; color: #000; font-weight: bold; }

        /* Time range row */
        .th-time { background: #ffff00; color: #000; font-size: 5.5pt; font-weight: normal; }

        /* Data: department cell */
        .td-dept { background: #c5cae9; color: #1a237e; font-weight: bold; text-align: left; padding-left: 3px; font-size: 6.5pt; }

        /* Data: semester cell */
        .td-sem { background: #ffa500; color: #000; font-weight: bold; }

        /* Data: lab cell */
        .td-lab { background: #fff3e0; }

        /* Course info */
        .course-code { font-weight: bold; font-size: 6.5pt; }
        .teacher { color: #37474f; font-size: 6pt; }

        /* Separators — visible stroke, not heavy bold */
        .day-sep  { border-right: 2px solid #777 !important; }
        .dept-sep { border-top: 2px solid #777 !important; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Master Routine: {{ now()->year }}</h2>
        <h3>{{ $setting->institution_name }}</h3>
    </div>

    <table>
        <thead>
            {{-- Row 1: Time | Day | Day names --}}
            <tr>
                <th class="lbl" style="width: 55px;">Time</th>
                <th class="lbl day-sep" style="width: 20px;">Day</th>
                @foreach($days as $dayIndex => $dayName)
                    <th class="th-day" colspan="{{ count($timeSlots) }}">{{ $dayName }}</th>
                @endforeach
            </tr>

            {{-- Row 2: Department | Period | Period numbers --}}
            <tr>
                <th class="lbl">Department</th>
                <th class="lbl day-sep">Period</th>
                @foreach($days as $dayIndex => $dayName)
                    @foreach($timeSlots as $ts)
                        <th class="{{ $loop->iteration % 2 === 1 ? 'th-period-odd' : 'th-period-even' }} {{ $loop->last ? 'day-sep' : '' }}">
                            {{ $loop->iteration }}
                        </th>
                    @endforeach
                @endforeach
            </tr>

            {{-- Row 3: (empty) | Semester | Time ranges --}}
            <tr>
                <th class="lbl"></th>
                <th class="lbl day-sep">Semester</th>
                @foreach($days as $dayIndex => $dayName)
                    @foreach($timeSlots as $ts)
                        <th class="th-time {{ $loop->last ? 'day-sep' : '' }}">
                            {{ \Carbon\Carbon::parse($ts->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($ts->end_time)->format('H:i') }}
                        </th>
                    @endforeach
                @endforeach
            </tr>
        </thead>

        <tbody>
            @php $prevDept = null; @endphp
            @foreach($reportRows as $key => $row)
                @php
                    $isNewDept = $row['dept_name'] !== $prevDept;
                    $deptSep   = $isNewDept ? 'dept-sep' : '';
                @endphp
                <tr>
                    @if($isNewDept)
                        @php
                            $deptRowCount = collect($reportRows)->where('dept_name', $row['dept_name'])->count();
                            $prevDept = $row['dept_name'];
                        @endphp
                        <td class="td-dept {{ $deptSep }}" rowspan="{{ $deptRowCount }}" style="vertical-align: middle;">
                            {{ $row['dept_name'] }}
                        </td>
                    @endif

                    <td class="td-sem day-sep {{ $deptSep }}">{{ $row['semester'] }}</td>

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
                                if ($span > 1) { $skipCount = $span - 1; }
                                $isLastSlot = $loop->remaining < $span;
                            @endphp
                            <td colspan="{{ $span }}"
                                class="{{ $span > 1 ? 'td-lab' : '' }} {{ $isLastSlot ? 'day-sep' : '' }} {{ $deptSep }}">
                                @if($cell)
                                    <div class="course-code">{{ $cell['course_code'] }}</div>
                                    <div class="teacher">{{ $cell['teacher_short'] }}</div>
                                @endif
                            </td>
                        @endforeach
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
