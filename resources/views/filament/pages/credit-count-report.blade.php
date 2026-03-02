<x-filament::page>
    <form wire:submit.prevent="generate" id="data" class="space-y-6">
        {{ $this->form }}
        <div style="margin-top: 1.5rem; text-align: center;">
            <x-filament::actions :actions="$this->getFormActions()" alignment="center" class="gap-x-4" />
        </div>
    </form>

    @if($this->departmentName && count($this->results) > 0)
        <div style="margin-top: 2rem;">
            <p class="report-title">
                {{ $this->departmentName }} &mdash; {{ $this->semesterLabel }}
            </p>

            <div class="report-wrapper">
                <table class="report-table">
                    <thead>
                        <tr class="report-header">
                            <th style="width: 36px;">#</th>
                            <th>Teacher</th>
                            <th>Courses</th>
                            <th class="report-cell--center" style="width: 100px;">Total Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->results as $i => $row)
                            <tr class="report-row{{ $loop->even ? ' report-row--even' : '' }}">
                                <td class="report-cell report-cell--num">{{ $i + 1 }}</td>
                                <td class="report-cell report-cell--bold" style="white-space: nowrap; vertical-align: top;">
                                    {{ $row['teacher_name'] }}
                                    @if($row['teacher_short'] !== $row['teacher_name'])
                                        <br><span class="report-cell--muted">({{ $row['teacher_short'] }})</span>
                                    @endif
                                </td>
                                <td class="report-cell" style="vertical-align: top;">
                                    @foreach($row['courses'] as $course)
                                        <div class="{{ !$loop->last ? 'report-course-divider' : '' }}" style="padding: 2px 0;">
                                            <span class="report-cell--code">{{ $course['code'] }}</span>
                                            &mdash; {{ $course['name'] }}
                                            <span class="report-cell--credit">({{ $course['credit_hours'] }} cr)</span>
                                        </div>
                                    @endforeach
                                </td>
                                <td class="report-cell report-cell--center report-cell--highlight">
                                    {{ number_format($row['total_credits'], 1) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="report-footer">
                            <td colspan="3" class="report-cell" style="text-align: right;">Grand Total</td>
                            <td class="report-cell report-cell--center report-cell--highlight">
                                {{ number_format(collect($this->results)->sum('total_credits'), 1) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @elseif($this->departmentName && count($this->results) === 0)
        <div class="report-empty">
            No routine assignments found for {{ $this->departmentName }} &mdash; {{ $this->semesterLabel }}.
        </div>
    @endif
</x-filament::page>
