<x-filament::page>
    <form wire:submit.prevent="generate" id="data" class="space-y-6">
        {{ $this->form }}
        <div style="margin-top: 1.5rem; text-align: center;">
            <x-filament::actions :actions="$this->getFormActions()" alignment="center" class="gap-x-4" />
        </div>
    </form>

    @if($this->hasResults)
        <div style="margin-top: 2rem; overflow-x: auto;">
            <table style="font-size: 11px; border-collapse: collapse; width: 100%;">
                <thead>
                    <tr>
                        <th style="padding: 8px 10px; background: #1e40af; color: white; text-align: left; border: 1px solid #1e3a8a; white-space: nowrap; min-width: 140px;">
                            Teacher
                        </th>
                        @foreach($this->days as $dayIndex => $dayName)
                            @foreach($this->timeSlots as $ts)
                                <th style="padding: 4px 6px; background: #166534; color: white; text-align: center; border: 1px solid #14532d; font-size: 9px; white-space: nowrap; min-width: 70px;">
                                    <div style="font-weight: bold;">{{ substr($dayName, 0, 3) }}</div>
                                    <div style="font-weight: normal; font-size: 8px;">{{ $ts->name }}</div>
                                </th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->teacherRows as $teacherId => $row)
                        <tr style="background: {{ $loop->even ? '#f8fafc' : 'white' }};">
                            <td style="padding: 6px 10px; border: 1px solid #cbd5e1; vertical-align: middle;">
                                <div style="font-weight: bold; font-size: 12px;">{{ $row['short'] }}</div>
                                <div style="color: #64748b; font-size: 10px;">{{ $row['name'] }}</div>
                                <div style="color: #3b82f6; font-size: 10px; font-weight: bold;">{{ $row['dept_code'] }}</div>
                            </td>
                            @foreach($this->days as $dayIndex => $dayName)
                                @foreach($this->timeSlots as $ts)
                                    @php $cell = $row['slots'][$dayIndex][$ts->id] ?? null; @endphp
                                    <td style="padding: 4px 6px; border: 1px solid #cbd5e1; text-align: center; vertical-align: middle; font-size: 10px;">
                                        @if($cell)
                                            <div style="font-weight: bold; color: #1d4ed8;">{{ $cell['course_code'] }}</div>
                                            <div style="color: #64748b; font-size: 9px;">{{ $cell['dept_code'] }} S{{ $cell['semester'] }}</div>
                                        @endif
                                    </td>
                                @endforeach
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::page>
