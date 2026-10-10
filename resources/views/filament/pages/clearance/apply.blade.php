<x-filament-panels::page>
    @php($eligibility = $this->eligibility())
    @php($student = $this->student())

    <x-filament::section heading="Your details">
        <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Name</dt><dd>{{ $student->profile?->full_name_certificate ?? $student->user->name }}</dd></div>
            <div><dt class="text-gray-500">Roll</dt><dd>{{ $student->roll_number }}</dd></div>
            <div><dt class="text-gray-500">Department</dt><dd>{{ $student->department?->name }}</dd></div>
            <div><dt class="text-gray-500">Program</dt><dd>{{ $student->program?->name }}</dd></div>
        </dl>
    </x-filament::section>

    @if ($eligibility->eligible())
        <x-filament::section>
            <p class="mb-4 text-sm" data-testid="eligible">You are eligible to apply for clearance.</p>
            {{ $this->submitAction }}
        </x-filament::section>
    @else
        <x-filament::section heading="You cannot apply yet">
            <ul class="list-disc space-y-1 pl-5 text-sm text-danger-600" data-testid="ineligible-reasons">
                @foreach ($eligibility->reasons as $reason)
                    <li data-code="{{ $reason['code'] }}">{{ $reason['message'] }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
