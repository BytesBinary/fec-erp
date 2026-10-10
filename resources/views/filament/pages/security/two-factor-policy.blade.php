<x-filament-panels::page>
    <x-filament::section :description="__('erp.security.policy_help')">
        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($this->getRoles() as $row)
                <li class="flex items-center justify-between gap-3 py-3" wire:key="role-{{ $row['role']->id }}">
                    <span>{{ \App\Enums\RoleKey::tryFrom($row['role']->name)?->label() ?? $row['role']->name }}</span>
                    <x-filament::button size="sm" :color="$row['required'] ? 'danger' : 'gray'" wire:click="toggleRole({{ $row['role']->id }})" data-role="{{ $row['role']->name }}">
                        {{ $row['required'] ? 'Required — click to make optional' : 'Optional — click to require' }}
                    </x-filament::button>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-panels::page>
