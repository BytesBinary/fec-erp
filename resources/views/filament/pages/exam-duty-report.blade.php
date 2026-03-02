<x-filament::page>
    <form wire:submit.prevent="generate" id="data" class="space-y-6">
        {{ $this->form }}
        <div style="margin-top: 1.5rem; text-align: center;">
            <x-filament::actions :actions="$this->getFormActions()" alignment="center" class="gap-x-4" />
        </div>
    </form>

    @if($this->examName && count($this->results) > 0)
        <div style="margin-top: 2rem;">
            <p class="report-title">
                {{ $this->examName }} &mdash; {{ $this->examYear }}
                &mdash; {{ ucfirst($this->reportType) }} Duty Count
            </p>

            <div class="report-wrapper">
                <table class="report-table">
                    <thead>
                        <tr class="report-header">
                            <th style="width: 36px;">#</th>
                            <th>Name</th>
                            <th class="report-cell--center" style="width: 180px;">
                                Number of {{ $this->reportType === 'invigilator' ? 'Invigilation' : 'Supervision' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->results as $i => $row)
                            <tr class="report-row{{ $loop->even ? ' report-row--even' : '' }}">
                                <td class="report-cell report-cell--num">{{ $i + 1 }}</td>
                                <td class="report-cell report-cell--bold">
                                    {{ $row['name'] }}
                                </td>
                                <td class="report-cell report-cell--center report-cell--highlight">
                                    {{ $row['duties'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="report-footer">
                            <td colspan="2" class="report-cell" style="text-align: right;">Total Duties</td>
                            <td class="report-cell report-cell--center report-cell--highlight">
                                {{ collect($this->results)->sum('duties') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @elseif($this->examName && count($this->results) === 0)
        <div class="report-empty">
            No {{ $this->reportType }} assignments found for {{ $this->examName }}.
        </div>
    @endif
</x-filament::page>
