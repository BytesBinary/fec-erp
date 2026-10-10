<x-filament-panels::page>
    <x-filament::section description="A new required field gates students whose profile no longer satisfies the set on their next request.">
        <table class="w-full text-left text-sm">
            <thead><tr><th class="py-2" scope="col">Field</th><th scope="col">Required</th><th scope="col">Active</th></tr></thead>
            <tbody>
            @foreach ($this->fields() as $field)
                <tr class="border-t border-gray-100 dark:border-white/5" wire:key="f-{{ $field->field_key }}">
                    <td class="py-2">{{ config('profile.fields.'.$field->field_key.'.label', $field->field_key) }}</td>
                    <td><x-filament::button size="xs" :color="$field->required ? 'success' : 'gray'" wire:click="toggleRequired('{{ $field->field_key }}')">{{ $field->required ? 'Required' : 'Optional' }}</x-filament::button></td>
                    <td><x-filament::button size="xs" :color="$field->active ? 'success' : 'gray'" wire:click="toggleActive('{{ $field->field_key }}')">{{ $field->active ? 'On' : 'Off' }}</x-filament::button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
