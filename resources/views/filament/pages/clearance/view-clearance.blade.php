<x-filament-panels::page>
    @php($clearance = $this->clearance())
    @php($dues = $this->dues())

    <x-filament::section>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Student</dt><dd data-testid="student-name">{{ $clearance->student->user->name }}</dd></div>
                <div><dt class="text-gray-500">Roll</dt><dd>{{ $clearance->student->roll_number }}</dd></div>
                <div><dt class="text-gray-500">Department</dt><dd>{{ $clearance->student->department?->name }}</dd></div>
                <div><dt class="text-gray-500">Program</dt><dd>{{ $clearance->student->program?->name }}</dd></div>
            </dl>
            <x-filament::badge :color="$clearance->status->color()" data-testid="clearance-status">{{ $clearance->status->label() }}</x-filament::badge>
        </div>
        <div class="mt-4 flex flex-wrap gap-3">
            {{ $this->approveAction }}
            {{ $this->rejectAction }}
            {{ $this->printAction }}
            {{ $this->printOriginalAction }}
            {{ $this->collectAction }}
            @if ($this->canPrint())
                <x-filament::button tag="a" color="gray" :href="route('clearance.print', $clearance->id)" icon="heroicon-o-eye">Open print view</x-filament::button>
                <x-filament::button tag="a" color="gray" :href="route('clearance.pdf', $clearance->id)" icon="heroicon-o-document-arrow-down">PDF</x-filament::button>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section heading="Approval timeline">
        <x-clearance.timeline :timeline="$this->timeline()" />
    </x-filament::section>

    @if ($dues['hall'] !== null)
        <x-filament::section heading="Hall dues">
            @forelse ($dues['hall'] as $due)
                <div class="flex justify-between text-sm" data-testid="hall-due">
                    <span>{{ $due->description }}</span>
                    <span>{{ number_format($due->amount, 2) }} — {{ $due->settled_at ? 'settled' : 'OPEN' }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No dues recorded.</p>
            @endforelse
        </x-filament::section>
    @endif

    @if ($dues['library'] !== null)
        <x-filament::section heading="Library">
            <p class="text-sm" data-testid="library-summary">
                Outstanding books: <strong>{{ $dues['library']['outstanding_loans'] }}</strong> ·
                Unpaid fines: <strong>{{ number_format($dues['library']['unpaid_fines'], 2) }}</strong>
            </p>
            <ul class="mt-2 list-disc pl-5 text-sm">
                @foreach ($dues['library']['items'] as $loan)
                    <li>{{ $loan->book_title }} — due {{ $loan->due_on->format('d M Y') }}@if (! $loan->returned_on) <span class="text-danger-600">(not returned)</span>@endif</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($this->canPrint())
        <x-filament::section heading="Print history">
            <ul class="space-y-1 text-sm" data-testid="print-history">
                @forelse ($clearance->prints()->with('printer')->get() as $print)
                    <li data-duplicate="{{ $print->is_duplicate ? '1' : '0' }}">{{ $print->printed_at->format('d M Y H:i') }} — {{ $print->printer?->name }}{{ $print->is_duplicate ? ' (DUPLICATE)' : '' }}</li>
                @empty
                    <li class="text-gray-500">Not printed yet.</li>
                @endforelse
            </ul>
            @if ($clearance->collected_at)
                <p class="mt-2 text-sm" data-testid="collected-info">Collected {{ $clearance->collected_at->format('d M Y H:i') }} by {{ $clearance->collector?->name }} — ID {{ $clearance->id_verified ? 'verified' : 'not verified' }}.</p>
            @endif
        </x-filament::section>
    @endif

    @php($integrity = $this->integrity())
    <x-filament::section heading="Integrity">
        <p class="text-sm {{ $integrity['intact'] ? 'text-success-700' : 'text-danger-700' }}" data-testid="integrity">
            {{ $integrity['intact'] ? 'Approval chain intact ('.$integrity['checked'].' entries).' : 'WARNING: the approval chain was altered.' }}
        </p>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
