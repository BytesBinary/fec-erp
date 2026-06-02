<x-filament::page>
    <form wire:submit.prevent="generate" id="data" class="space-y-6">
        {{ $this->form }}
        <div style="margin-top: 1.5rem; text-align: center;">
            <x-filament::actions :actions="$this->getFormActions()" alignment="center" class="gap-x-4" />
        </div>
    </form>

    @if($this->hasResults)
        <div style="margin-top: 2rem; overflow-x: auto;">
            <table class="report-table" style="font-size: 11px; border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="background: #1e40af; color: white;">
                        <th style="padding: 6px 8px; text-align: left; white-space: nowrap; border: 1px solid #1e3a8a;">Department</th>
                        <th style="padding: 6px 8px; text-align: center; white-space: nowrap; border: 1px solid #1e3a8a;">Sem</th>
                        @foreach($this->days as $dayIndex => $dayName)
                            @foreach($this->timeSlots as $ts)
                                <th style="padding: 4px 6px; text-align: center; border: 1px solid #1e3a8a; font-size: 10px;">
                                    <div style="font-weight: bold;">{{ substr($dayName, 0, 3) }}</div>
                                    <div style="font-weight: normal; font-size: 9px;">{{ $ts->name }}</div>
                                </th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php $prevDept = null; @endphp
                    @foreach($this->reportRows as $key => $row)
                        <tr style="background: {{ $loop->even ? '#f8fafc' : 'white' }}; border-bottom: 1px solid #e2e8f0;">
                            @if($row['dept_name'] !== $prevDept)
                                @php
                                    $deptRowCount = collect($this->reportRows)->where('dept_name', $row['dept_name'])->count();
                                    $prevDept = $row['dept_name'];
                                @endphp
                                <td rowspan="{{ $deptRowCount }}"
                                    style="padding: 6px 8px; font-weight: bold; border: 1px solid #cbd5e1; vertical-align: middle; background: #eff6ff; color: #1e40af; white-space: nowrap;">
                                    {{ $row['dept_name'] }}
                                </td>
                            @endif
                            <td style="padding: 6px 8px; text-align: center; border: 1px solid #cbd5e1; font-weight: bold; color: #dc2626; white-space: nowrap;">
                                {{ $row['semester'] }}
                            </td>
                            @foreach($this->days as $dayIndex => $dayName)
                                @php $skipCount = 0; @endphp
                                @foreach($this->timeSlots as $ts)
                                    @if($skipCount > 0)
                                        @php $skipCount--; @endphp
                                        @continue
                                    @endif
                                    @php
                                        $cell = $row['slots'][$dayIndex][$ts->id] ?? null;
                                        $span = $cell ? ($cell['lab_span'] ?? 1) : 1;
                                        if ($span > 1) $skipCount = $span - 1;
                                    @endphp
                                    <td colspan="{{ $span }}"
                                        style="padding: 4px 5px; text-align: center; border: 1px solid #cbd5e1; font-size: 10px; vertical-align: middle;
                                               {{ $span > 1 ? 'background: #fef3c7;' : '' }}">
                                        @if($cell)
                                            <div style="font-weight: bold;">{{ $cell['course_code'] }}</div>
                                            <div style="color: #475569;">{{ $cell['teacher_short'] }}</div>
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
