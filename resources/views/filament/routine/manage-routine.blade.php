<x-filament-panels::page>
    @php
        $batch = $this->record;
        $days = $this->dayNames;
        $timeSlots = $this->timeSlots();
    @endphp

    @if (! $batch->is_active)
        <div class="rounded-lg bg-danger-50 border border-danger-200 p-4 text-danger-700">
            This batch is inactive. Activate the batch to manage its routine.
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 shadow">
            <table class="routine-table">
                <thead>
                    <tr>
                        <th class="routine-header-cell">
                            Day
                        </th>
                        @foreach ($timeSlots as $slot)
                            @if ($slot->type === \App\Enums\CourseType::Break)
                                <th class="routine-header-cell" style="background:#6b7280; min-width:80px;">
                                    <div>Break</div>
                                    <div style="font-size:0.7em; font-weight:normal; opacity:0.85;">
                                        {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}<br>{{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                                    </div>
                                </th>
                            @else
                                <th class="routine-header-cell routine-header-cell--day">
                                    <div>{{ $slot->name }}</div>
                                    <div style="font-size:0.7em; font-weight:normal; opacity:0.85;">
                                        {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}<br>{{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                                    </div>
                                    <span class="slot-type-badge {{ $slot->type === \App\Enums\CourseType::Theory ? 'slot-type-badge--theory' : 'slot-type-badge--lab' }}" style="font-size:0.65em;">
                                        {{ $slot->type->label() }}
                                    </span>
                                </th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $dayIndex => $dayName)
                        <tr class="routine-row">
                            <td class="slot-label-cell">
                                <div class="slot-label-name">{{ $dayName }}</div>
                            </td>
                            @foreach ($timeSlots as $slot)
                                @if ($slot->type === \App\Enums\CourseType::Break)
                                    <td style="background:#f3f4f6; text-align:center; color:#9ca3af; font-size:0.75em; vertical-align:middle;">
                                        Break
                                    </td>
                                @else
                                    <td class="routine-cell">
                                        @if (isset($this->assignments[$dayIndex][$slot->id]))
                                            @php $a = $this->assignments[$dayIndex][$slot->id] @endphp
                                            <div class="{{ str_contains($a['course_type'], 'Lab') ? 'routine-cell--assigned-lab' : 'routine-cell--assigned-theory' }}">
                                                <div class="assigned-course-code">{{ $a['course_code'] }}</div>
                                                <div class="assigned-course-name" title="{{ $a['course_name'] }}">{{ $a['course_name'] }}</div>
                                                <div class="assigned-teacher">{{ $a['teacher_short'] }}</div>
                                                <button
                                                    type="button"
                                                    wire:click="clearSlot({{ $dayIndex }}, {{ $slot->id }})"
                                                    wire:confirm="Remove this assignment?"
                                                    class="slot-remove-btn"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        @else
                                            @php $opts = $this->options[$dayIndex][$slot->id] ?? [] @endphp
                                            @if (empty($opts))
                                                <div class="slot-empty">—</div>
                                            @else
                                                <select
                                                    class="slot-select"
                                                    @change="$wire.assignSlot({{ $dayIndex }}, {{ $slot->id }}, $event.target.value)"
                                                >
                                                    <option value="">— Assign —</option>
                                                    @foreach ($opts as $val => $label)
                                                        <option value="{{ $val }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
