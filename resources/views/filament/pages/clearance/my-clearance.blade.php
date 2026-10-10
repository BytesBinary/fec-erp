<x-filament-panels::page>
    @php($clearance = $this->clearance())

    @if ($clearance === null)
        <x-filament::section>
            <p class="text-sm">You have not applied for clearance.</p>
            <x-filament::button tag="a" :href="\App\Filament\Pages\Clearance\Apply::getUrl()" class="mt-4">Apply for clearance</x-filament::button>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-sm text-gray-500">Request number</div>
                    <div class="text-lg font-semibold" data-testid="request-no">{{ $clearance->request_no }}</div>
                </div>
                <x-filament::badge :color="$clearance->status->color()" data-testid="clearance-status">{{ $clearance->status->label() }}</x-filament::badge>
            </div>

            @if ($clearance->status === \App\Enums\ClearanceStatus::ReadyForCollection || $clearance->status === \App\Enums\ClearanceStatus::Printed)
                <p class="mt-4 rounded-lg bg-success-50 p-3 text-sm text-success-800" data-testid="ready-message">{{ __('erp.clearance.ready_message') }}</p>
            @elseif ($clearance->status === \App\Enums\ClearanceStatus::Collected)
                <p class="mt-4 rounded-lg bg-success-50 p-3 text-sm text-success-800" data-testid="collected-message">{{ __('erp.clearance.collected_message') }}</p>
            @elseif ($clearance->status === \App\Enums\ClearanceStatus::Rejected)
                <p class="mt-4 rounded-lg bg-danger-50 p-3 text-sm text-danger-800" data-testid="rejected-message">
                    Rejected at {{ $clearance->currentStage?->label }}. Fix the problem described in the remarks below, then resubmit.
                </p>
            @endif

            <div class="mt-4 flex flex-wrap gap-3">
                {{ $this->resubmitAction }}
                {{ $this->cancelAction }}
            </div>
        </x-filament::section>

        <x-filament::section heading="Progress">
            <x-clearance.timeline :timeline="$this->timeline($clearance)" />
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
