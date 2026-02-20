<x-filament-panels::page>
    <div style="margin-bottom: 1rem;">
        <p><strong>Exam:</strong> {{ $this->examDuty['exam_name'] }} ({{ $this->examDuty['exam_year'] }})</p>
        <p><strong>Time:</strong> {{ $this->examDuty['start_time'] }} – {{ $this->examDuty['end_time'] }}</p>
    </div>

    <div class="overflow-x-auto">
        <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
            <thead>
                <tr style="background:#1e3a5f; color:#fff;">
                    <th style="border:1px solid #bbb; padding:8px;">Date</th>
                    <th style="border:1px solid #bbb; padding:8px;">Invigilator(s)</th>
                    <th style="border:1px solid #bbb; padding:8px;">Course(s)</th>
                    <th style="border:1px solid #bbb; padding:8px;">Exam Hall(s)</th>
                    <th style="border:1px solid #bbb; padding:8px;">Supervisor(s)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->examDuty['duty_details'] as $detail)
                    <tr>
                        <td style="border:1px solid #bbb; padding:8px; text-align:center;">{{ $detail['date'] }}</td>
                        <td style="border:1px solid #bbb; padding:8px;">
                            @foreach($detail['invigilator'] as $name)
                                <div>{{ $name }}</div>
                            @endforeach
                        </td>
                        <td style="border:1px solid #bbb; padding:8px;">
                            @foreach($detail['course'] as $course)
                                <div>{{ $course }}</div>
                            @endforeach
                        </td>
                        <td style="border:1px solid #bbb; padding:8px;">
                            @foreach($detail['exam_hall'] as $hall)
                                <div>{{ $hall }}</div>
                            @endforeach
                        </td>
                        <td style="border:1px solid #bbb; padding:8px;">
                            @foreach($detail['supervisor'] as $name)
                                <div>{{ $name }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="border:1px solid #bbb; padding:8px; text-align:center; color:#999;">No duty details found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
