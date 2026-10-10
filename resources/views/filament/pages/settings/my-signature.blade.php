<x-filament-panels::page>
    <x-filament::section heading="Current signature">
        @if ($uri = $this->currentDataUri())
            <img src="{{ $uri }}" alt="Your current signature" class="h-16 bg-white" data-testid="current-signature" />
        @else
            <p class="text-sm text-warning-700">No signature uploaded yet. You cannot approve clearances without one.</p>
        @endif
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::actions :actions="$this->getFormActions()" />
    </form>
</x-filament-panels::page>
