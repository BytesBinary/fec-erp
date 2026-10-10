<x-filament-panels::page>
    @php($transcript = $this->transcript())

    <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex gap-8">
                <div>
                    <div class="text-sm text-gray-500">{{ __('erp.results.cgpa') }}</div>
                    <div class="text-3xl font-semibold" data-testid="cgpa">{{ $transcript['cgpa_display'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('erp.results.credits_earned') }}</div>
                    <div class="text-3xl font-semibold" data-testid="credits-earned">{{ rtrim(rtrim(number_format($transcript['earned_credits'], 2), '0'), '.') }}</div>
                </div>
            </div>
            @if ($transcript['semesters'] !== [])
                <x-filament::button tag="a" :href="route('results.print')" target="_blank" icon="heroicon-o-printer" color="gray">{{ __('erp.results.print') }}</x-filament::button>
            @endif
        </div>
    </x-filament::section>

    @forelse ($transcript['semesters'] as $semester)
        <x-filament::section :heading="$semester['name']" data-testid="semester-block">
            <x-slot name="afterHeader">
                <span class="text-sm">{{ __('erp.results.semester_gpa') }}: <strong data-testid="semester-gpa">{{ $semester['gpa_display'] }}</strong></span>
            </x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="py-2 pr-4" scope="col">Code</th>
                            <th class="py-2 pr-4" scope="col">Course</th>
                            <th class="py-2 pr-4" scope="col">Credits</th>
                            <th class="py-2 pr-4" scope="col">Grade</th>
                            <th class="py-2" scope="col">Grade point</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($semester['courses'] as $course)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pr-4">{{ $course['code'] }}</td>
                                <td class="py-2 pr-4">{{ $course['name'] }}@if ($course['attempt'] !== 'regular') <span class="text-xs text-gray-500">({{ __('erp.attempt_types.'.$course['attempt']) }})</span>@endif</td>
                                <td class="py-2 pr-4">{{ $course['credits'] }}</td>
                                <td class="py-2 pr-4">{{ $course['letter'] }}</td>
                                <td class="py-2">{{ number_format($course['grade_point'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @empty
        <x-filament::section><p class="text-sm text-gray-500">{{ __('erp.results.empty') }}</p></x-filament::section>
    @endforelse

    @php($portalResults = $this->portalResults())
    @if ($portalResults->isNotEmpty())
        <x-filament::section heading="Official results (exam portal)" description="Pulled automatically from the university exam portal. A grade marked Improved, Retake or Declined replaced an earlier attempt." data-testid="portal-results">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="py-2 pr-4" scope="col">Course</th>
                            <th class="py-2 pr-4" scope="col">Grade</th>
                            <th class="py-2 pr-4" scope="col">Grade point</th>
                            <th class="py-2 pr-4" scope="col">Update</th>
                            <th class="py-2" scope="col">Exam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($portalResults as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5" data-testid="portal-result-row">
                                <td class="py-2 pr-4">{{ $row->course_code }}@if ($row->course_title) <span class="text-xs text-gray-500">{{ $row->course_title }}</span>@endif</td>
                                <td class="py-2 pr-4">{{ $row->letter }}</td>
                                <td class="py-2 pr-4">{{ number_format((float) $row->grade_point, 2) }}</td>
                                <td class="whitespace-nowrap py-2 pr-4">
                                    @if ($row->change_type)
                                        <x-filament::badge :color="$row->change_type->color()" class="!max-w-none whitespace-nowrap" data-testid="portal-change">{{ $row->change_type->label() }} ({{ $row->previous_letter }} → {{ $row->letter }})</x-filament::badge>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 text-xs text-gray-500">{{ $row->exam_title }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
