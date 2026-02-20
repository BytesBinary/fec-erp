<x-filament::page>
    <form wire:submit.prevent="generate" id="data" class="space-y-6">
        {{ $this->form }}
        <div style="margin-top" 1.5rem; text-align: center;">
            <x-filament::actions :actions="$this->getFormActions()" alignment="center" class="gap-x-4" />
        </div>
    </form>

    @if($this->departmentName && count($this->results) > 0)
        <div style="margin-top: 2rem;">
            <p style="font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.75rem;">
                {{ $this->departmentName }} &mdash; {{ $this->semesterLabel }}
            </p>

            <div style="overflow-x: auto; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="background-color: #1e3a5f; color: #fff;">
                            <th style="padding: 10px 14px; text-align: left; font-weight: 600; border: 1px solid #334f74; width: 36px;">#</th>
                            <th style="padding: 10px 14px; text-align: left; font-weight: 600; border: 1px solid #334f74;">Teacher</th>
                            <th style="padding: 10px 14px; text-align: left; font-weight: 600; border: 1px solid #334f74;">Courses</th>
                            <th style="padding: 10px 14px; text-align: center; font-weight: 600; border: 1px solid #334f74; width: 100px;">Total Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->results as $i => $row)
                            <tr style="background-color: {{ $loop->even ? '#f9fafb' : '#ffffff' }};">
                                <td style="padding: 10px 14px; border: 1px solid #e5e7eb; color: #9ca3af; text-align: center;">{{ $i + 1 }}</td>
                                <td style="padding: 10px 14px; border: 1px solid #e5e7eb; font-weight: 600; white-space: nowrap; vertical-align: top;">
                                    {{ $row['teacher_name'] }}
                                    @if($row['teacher_short'] !== $row['teacher_name'])
                                        <br><span style="font-size: 0.75rem; color: #9ca3af; font-weight: 400;">({{ $row['teacher_short'] }})</span>
                                    @endif
                                </td>
                                <td style="padding: 10px 14px; border: 1px solid #e5e7eb; vertical-align: top;">
                                    @foreach($row['courses'] as $course)
                                        <div style="padding: 2px 0; {{ !$loop->last ? 'border-bottom: 1px dotted #e5e7eb;' : '' }}">
                                            <span style="font-weight: 700; color: #1e3a5f; font-family: monospace;">{{ $course['code'] }}</span>
                                            &mdash; {{ $course['name'] }}
                                            <span style="color: #9ca3af; font-size: 0.8rem;">({{ $course['credit_hours'] }} cr)</span>
                                        </div>
                                    @endforeach
                                </td>
                                <td style="padding: 10px 14px; border: 1px solid #e5e7eb; text-align: center; font-weight: 700; font-size: 1rem; color: #1e3a5f;">
                                    {{ number_format($row['total_credits'], 1) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f3f4f6; font-weight: 700;">
                            <td colspan="3" style="padding: 10px 14px; border: 1px solid #e5e7eb; text-align: right; color: #374151;">Grand Total</td>
                            <td style="padding: 10px 14px; border: 1px solid #e5e7eb; text-align: center; font-size: 1rem; color: #1e3a5f;">
                                {{ number_format(collect($this->results)->sum('total_credits'), 1) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @elseif($this->departmentName && count($this->results) === 0)
        <div style="margin-top: 2rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.5rem; text-align: center; color: #9ca3af;">
            No routine assignments found for {{ $this->departmentName }} &mdash; {{ $this->semesterLabel }}.
        </div>
    @endif
</x-filament::page>
