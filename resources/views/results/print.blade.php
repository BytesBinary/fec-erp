<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Result sheet — {{ $student->user->name }}</title>
    <style>
        @page { size: A4; margin: 18mm; }
        body { font-family: system-ui, sans-serif; color: #111; margin: 0; padding: 24px; }
        h1, h2 { margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 20px; font-size: 13px; }
        th, td { border: 1px solid #999; padding: 4px 8px; text-align: left; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin: 12px 0 20px; font-size: 14px; }
        .head { text-align: center; margin-bottom: 16px; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print"><button type="button" onclick="window.print()">Print</button></div>
    <div class="head">
        <h1>{{ $institution->institution_name }}</h1>
        <div>Result sheet</div>
    </div>
    <div class="meta">
        <div><strong>Name:</strong> {{ $student->user->name }}</div>
        <div><strong>Roll:</strong> {{ $student->roll_number }}</div>
        <div><strong>Department:</strong> {{ $student->department?->name }}</div>
        <div><strong>Program:</strong> {{ $student->program?->name }}</div>
        <div><strong>CGPA:</strong> <span data-testid="print-cgpa">{{ $transcript['cgpa_display'] }}</span></div>
        <div><strong>Credits earned:</strong> {{ $transcript['earned_credits'] }}</div>
    </div>
    @foreach ($transcript['semesters'] as $semester)
        <h2>{{ $semester['name'] }} — GPA {{ $semester['gpa_display'] }}</h2>
        <table>
            <thead><tr><th>Code</th><th>Course</th><th>Credits</th><th>Grade</th><th>Grade point</th></tr></thead>
            <tbody>
            @foreach ($semester['courses'] as $course)
                <tr><td>{{ $course['code'] }}</td><td>{{ $course['name'] }}</td><td>{{ $course['credits'] }}</td><td>{{ $course['letter'] }}</td><td>{{ number_format($course['grade_point'], 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endforeach
    <p style="font-size:12px">Only published results are shown. Generated {{ now()->format('d M Y H:i') }}.</p>
</body>
</html>
