<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 8pt; color: #000; }
        .header { text-align: center; margin-bottom: 10px; }
        .header h2 { font-size: 13pt; font-weight: bold; }
        .header h3 { font-size: 10pt; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 3px 4px; text-align: center; vertical-align: middle; font-size: 7.5pt; }
        .th-teacher { background: #1a237e; color: #fff; font-weight: bold; text-align: left; width: 100px; }
        .th-day { background: #1b5e20; color: #fff; font-weight: bold; font-size: 8pt; }
        .th-period { background: #e8f5e9; color: #1b5e20; font-size: 7pt; }
        .teacher-name { font-weight: bold; font-size: 8pt; }
        .teacher-dept { color: #1a237e; font-size: 7pt; font-weight: bold; }
        .course-code { font-weight: bold; color: #1565c0; font-size: 8pt; }
        .course-dept { color: #546e7a; font-size: 6.5pt; }
        .even-row { background: #f5f5f5; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Individual Routine — {{ now()->year }}</h2>
        <h3>{{ $setting->institution_name }}</h3>
    </div>

    <table>
        <thead>
            <tr>
                <th class="th-teacher" rowspan="2">Teacher</th>
                @foreach($days as $dayIndex => $dayName)
                    <th class="th-day" colspan="{{ count($timeSlots) }}">{{ $dayName }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($days as $dayIndex => $dayName)
                    @foreach($timeSlots as $ts)
                        <th class="th-period">
                            <div>{{ $ts->name }}</div>
                            <div style="font-size: 6pt; font-weight: normal;">
                                {{ \Carbon\Carbon::parse($ts->start_time)->format('H:i') }}
                            </div>
                        </th>
                    @endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($teacherRows as $teacherId => $row)
                <tr class="{{ $loop->even ? 'even-row' : '' }}">
                    <td class="th-teacher" style="text-align: left; padding: 4px 5px;">
                        <div class="teacher-name">{{ $row['short'] }}</div>
                        <div class="teacher-dept">{{ $row['dept_code'] }}</div>
                    </td>
                    @foreach($days as $dayIndex => $dayName)
                        @foreach($timeSlots as $ts)
                            @php $cell = $row['slots'][$dayIndex][$ts->id] ?? null; @endphp
                            <td>
                                @if($cell)
                                    <div class="course-code">{{ $cell['course_code'] }}</div>
                                    <div class="course-dept">{{ $cell['dept_code'] }} S{{ $cell['semester'] }}</div>
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
