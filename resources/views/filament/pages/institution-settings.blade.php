<x-filament::page>
    <form wire:submit.prevent="save" id="data" class="space-y-6">
        {{ $this->form }}
        <x-filament::actions :actions="$this->getFormActions()" alignment="center" class="gap-x-4" />
    </form>
</x-filament::page>
