<x-filament-panels::page>
    <form wire:submit.prevent="save" id="data" class="space-y-6">
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            @if(empty($record))
                <x-filament::actions
                    :actions="$this->getformactions()"
                    alignment="center"
                    class="gap-x-4"
                />
            @else
                <x-filament::actions
                    :actions="$this->getformactions('update')"
                    alignment="center"
                    class="gap-x-4"
                />
            @endif
        </div>
    </form>
</x-filament-panels::page>
