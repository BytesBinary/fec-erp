<x-filament::page>
    <form wire:submit.prevent="generate" id="data" class="space-y-6">
        {{ $this->form }}
        <div style="margin-top: 1.5rem; text-align: center;">
            <x-filament::actions :actions="$this->getFormActions()" alignment="center" class="gap-x-4" />
        </div>
    </form>

    @if($this->hasResults)
        <div style="margin-top: 2rem; overflow-x: auto;">
            <table style="border-collapse: collapse; width: 100%; font-size: 11px; font-family: Arial, sans-serif;">
                <thead>
                    {{-- Row 1: Time | Day | Day names --}}
                    <tr>
                        <th style="border: 1px solid #aaa; padding: 4px 6px; background: #ffff00; color: #000; font-weight: bold; white-space: nowrap;">Time</th>
                        <th style="border: 1px solid #aaa; border-right: 2px solid #777; padding: 4px 6px; background: #ffff00; color: #000; font-weight: bold; white-space: nowrap;">Day</th>
                        @foreach($this->days as $dayIndex => $dayName)
                            <th colspan="{{ count($this->timeSlots) }}"
                                style="border: 1px solid #aaa; border-right: 2px solid #777; padding: 4px 6px; background: #1a237e; color: #fff; font-weight: bold; text-align: center;">
                                {{ $dayName }}
                            </th>
                        @endforeach
                    </tr>

                    {{-- Row 2: Department | Period | Period numbers per day --}}
                    <tr>
                        <th style="border: 1px solid #aaa; padding: 4px 6px; background: #ffff00; color: #000; font-weight: bold; white-space: nowrap;">Department</th>
                        <th style="border: 1px solid #aaa; border-right: 2px solid #777; padding: 4px 6px; background: #ffff00; color: #000; font-weight: bold; white-space: nowrap;">Period</th>
                        @foreach($this->days as $dayIndex => $dayName)
                            @foreach($this->timeSlots as $ts)
                                <th style="border: 1px solid #aaa; {{ $loop->last ? 'border-right: 2px solid #777;' : '' }}
                                           padding: 3px 5px; font-weight: bold; text-align: center;
                                           background: {{ $loop->iteration % 2 === 1 ? '#ffff00' : '#ffa500' }}; color: #000;">
                                    {{ $loop->iteration }}
                                </th>
                            @endforeach
                        @endforeach
                    </tr>

                    {{-- Row 3: (empty) | Semester | Time ranges per day --}}
                    <tr>
                        <th style="border: 1px solid #aaa; padding: 4px 6px; background: #ffff00;"></th>
                        <th style="border: 1px solid #aaa; border-right: 2px solid #777; padding: 4px 6px; background: #ffff00; color: #000; font-weight: bold; white-space: nowrap;">Semester</th>
                        @foreach($this->days as $dayIndex => $dayName)
                            @foreach($this->timeSlots as $ts)
                                <th style="border: 1px solid #aaa; {{ $loop->last ? 'border-right: 2px solid #777;' : '' }}
                                           padding: 3px 4px; background: #ffff00; color: #000; font-size: 9px; font-weight: normal; white-space: nowrap;">
                                    {{ \Carbon\Carbon::parse($ts->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($ts->end_time)->format('H:i') }}
                                </th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @php $prevDept = null; @endphp
                    @foreach($this->reportRows as $key => $row)
                        @php
                            $isNewDept = $row['dept_name'] !== $prevDept;
                            $deptTop   = $isNewDept ? 'border-top: 2px solid #777;' : '';
                        @endphp
                        <tr>
                            @if($isNewDept)
                                @php
                                    $deptRowCount = collect($this->reportRows)->where('dept_name', $row['dept_name'])->count();
                                    $prevDept = $row['dept_name'];
                                @endphp
                                <td rowspan="{{ $deptRowCount }}"
                                    style="border: 1px solid #cbd5e1; {{ $deptTop }} padding: 6px 8px; background: #c5cae9; color: #1a237e;
                                           font-weight: bold; text-align: left; vertical-align: middle; white-space: nowrap;">
                                    {{ $row['dept_name'] }}
                                </td>
                            @endif

                            <td style="border: 1px solid #aaa; border-right: 2px solid #777; {{ $deptTop }} padding: 4px 6px; background: #ffa500; color: #000; font-weight: bold; text-align: center;">
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
                                        if ($span > 1) { $skipCount = $span - 1; }
                                        $isLastSlot = $loop->remaining < $span;
                                    @endphp
                                    <td colspan="{{ $span }}"
                                        style="border: 1px solid #aaa;
                                               {{ $isLastSlot ? 'border-right: 2px solid #777;' : '' }}
                                               {{ $deptTop }}
                                               {{ $span > 1 ? 'background: #fff3e0;' : '' }}
                                               padding: 3px 4px; text-align: center; vertical-align: middle;">
                                        @if($cell)
                                            <div style="font-weight: bold; font-size: 10px;">{{ $cell['course_code'] }}</div>
                                            <div style="color: #475569; font-size: 9px;">{{ $cell['teacher_short'] }}</div>
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
