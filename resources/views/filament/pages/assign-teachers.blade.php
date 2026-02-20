<x-filament::page>
    <form wire:submit.prevent="save" id="data" class="space-y-6">
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            <x-filament::actions
                :actions="$this->getFormActions()"
                alignment="center"
                class="gap-x-4"
            />
        </div>
    </form>
</x-filament::page>
