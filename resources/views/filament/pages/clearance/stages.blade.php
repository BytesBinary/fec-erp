<x-filament-panels::page>
    <x-filament::section description="Stages run strictly in this order. A skippable hall stage is skipped for non-residential students.">
        <table class="w-full text-left text-sm">
            <thead><tr><th class="py-2" scope="col">#</th><th scope="col">Stage</th><th scope="col">Approver role</th><th scope="col">Scope</th><th scope="col">Skippable</th><th scope="col">Active</th><th scope="col">Order</th></tr></thead>
            <tbody>
            @foreach ($this->stages() as $stage)
                <tr class="border-t border-gray-100 dark:border-white/5" wire:key="stage-{{ $stage->id }}" data-stage="{{ $stage->key }}">
                    <td class="py-2">{{ $stage->order }}</td>
                    <td>{{ $stage->label }}</td>
                    <td>{{ \App\Enums\RoleKey::tryFrom($stage->approverRole->name)?->label() ?? $stage->approverRole->name }}</td>
                    <td>{{ $stage->scope_rule }}</td>
                    <td><x-filament::button size="xs" :color="$stage->skippable ? 'success' : 'gray'" wire:click="toggle({{ $stage->id }}, 'skippable')">{{ $stage->skippable ? 'Yes' : 'No' }}</x-filament::button></td>
                    <td><x-filament::button size="xs" :color="$stage->active ? 'success' : 'gray'" wire:click="toggle({{ $stage->id }}, 'active')">{{ $stage->active ? 'On' : 'Off' }}</x-filament::button></td>
                    <td>
                        <x-filament::button size="xs" color="gray" wire:click="move({{ $stage->id }}, 'up')" aria-label="Move up">↑</x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="move({{ $stage->id }}, 'down')" aria-label="Move down">↓</x-filament::button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
