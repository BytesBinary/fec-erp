<x-filament-panels::page>
    @php($status = $this->status())

    @unless ($status['enabled'])
        <x-filament::section><p class="text-sm">The result portal sync is switched off (<code>RESULT_PORTAL_ENABLED=false</code>).</p></x-filament::section>
    @endunless

    @if ($status['shadow_mode'])
        <x-filament::section>
            <p class="text-sm"><strong>Shadow mode is on.</strong> New publications are detected and confirmed, but no student pulls are queued and no emails are sent until you press <em>Run now</em> on a publication below.</p>
        </x-filament::section>
    @endif

    <div class="grid gap-4 md:grid-cols-4" data-testid="monitor-cards">
        <x-filament::section>
            <div class="text-sm text-gray-500">Last successful check</div>
            <div class="text-lg font-semibold" data-testid="last-success">{{ $status['last_success']?->finished_at?->diffForHumans() ?? 'Never' }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">Next scheduled check</div>
            <div class="text-lg font-semibold" data-testid="next-check">{{ $status['next_check_at']->format('d M Y, H:i') }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">Student pulls</div>
            <div class="text-sm" data-testid="pull-counts">
                @forelse ($status['pull_counts'] as $name => $count)
                    <x-filament::badge class="mr-1" :color="App\Enums\ResultPullStatus::from($name)->color()">{{ App\Enums\ResultPullStatus::from($name)->label() }}: {{ $count }}</x-filament::badge>
                @empty
                    —
                @endforelse
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">Last problem</div>
            <div class="text-sm" data-testid="last-failure">{{ $status['last_failure'] ? $status['last_failure']->finished_at?->diffForHumans().': '.\Illuminate\Support\Str::limit((string) $status['last_failure']->message, 90) : 'None' }}</div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Exams saved from the portal" description="Grouped by department and exam year.">
        @forelse ($status['exams_by_program'] as $program)
            <div class="mb-3" data-testid="exam-program">
                <div class="font-medium">{{ $program['label'] }} — {{ $program['total'] }} exams</div>
                <div class="mt-1 flex flex-wrap gap-2 text-sm">
                    @foreach ($program['by_year'] as $year => $count)
                        <x-filament::badge color="gray">{{ $year }}: {{ $count }}</x-filament::badge>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No exams saved yet. Press <em>First-time sync</em>.</p>
        @endforelse
    </x-filament::section>

    <x-filament::section heading="Detected publications" description="A new exam id on the portal, confirmed by probe students.">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="py-2 pr-4" scope="col">Exam</th>
                        <th class="py-2 pr-4" scope="col">Status</th>
                        <th class="py-2 pr-4" scope="col">Detected</th>
                        <th class="py-2 pr-4" scope="col">Students</th>
                        <th class="py-2" scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($status['publications'] as $publication)
                        <tr class="border-b border-gray-100 dark:border-white/5" data-testid="publication-row">
                            <td class="py-2 pr-4">{{ App\Models\PortalExam::query()->where('portal_exam_id', $publication->portal_exam_id)->value('title') }}</td>
                            <td class="whitespace-nowrap py-2 pr-4"><x-filament::badge :color="match ($publication->status) { 'confirmed', 'complete' => 'success', 'shadow' => 'warning', 'awaiting' => 'gray', default => 'info' }">{{ ucfirst($publication->status) }}</x-filament::badge></td>
                            <td class="py-2 pr-4">{{ $publication->detected_at->format('d M Y') }}</td>
                            <td class="py-2 pr-4">{{ $publication->students_total ?: '—' }}</td>
                            <td class="whitespace-nowrap py-2">
                                @if ($this->canManage() && in_array($publication->status, ['shadow', 'confirmed'], true))
                                    <x-filament::button size="xs" wire:click="runPublication({{ $publication->id }})" wire:confirm="Queue a result pull for every eligible student of this exam?">Run now</x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-gray-500">No publication detected yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <details>
            <summary class="cursor-pointer text-base font-semibold">Recent probe checks</summary>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($status['probes'] as $probe)
                <li data-testid="probe-row">{{ $probe->checked_at->format('d M H:i') }} — exam {{ $probe->portal_exam_id }}: <strong>{{ str_replace('_', ' ', $probe->outcome) }}</strong> @if ($probe->message)<span class="text-gray-500">({{ \Illuminate\Support\Str::limit($probe->message, 80) }})</span>@endif</li>
            @empty
                <li class="text-gray-500">None yet.</li>
            @endforelse
        </ul>
        </details>
    </x-filament::section>

    <x-filament::section>
        <details>
            <summary class="cursor-pointer text-base font-semibold">Recent runs</summary>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($status['runs'] as $run)
                <li>{{ $run->started_at->format('d M H:i') }} — {{ $run->kind }}: <strong>{{ $run->status }}</strong>, {{ $run->new_exams }} new exam(s), {{ $run->publications_confirmed }} confirmed @if ($run->message)<span class="text-gray-500">— {{ \Illuminate\Support\Str::limit($run->message, 100) }}</span>@endif</li>
            @empty
                <li class="text-gray-500">None yet.</li>
            @endforelse
        </ul>
        </details>
    </x-filament::section>
</x-filament-panels::page>
