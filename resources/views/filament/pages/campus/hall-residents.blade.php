<x-filament-panels::page>
    <x-filament::section heading="Residents">
        <table class="w-full text-left text-sm">
            <thead><tr><th class="py-2" scope="col">Student</th><th scope="col">Roll</th><th scope="col">Hall</th><th scope="col">Room</th></tr></thead>
            <tbody>
            @foreach ($this->residents() as $residency)
                <tr class="border-t border-gray-100 dark:border-white/5"><td class="py-2">{{ $residency->student->user->name }}</td><td>{{ $residency->student->roll_number }}</td><td>{{ $residency->hall->code }}</td><td>{{ $residency->room }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="Assign a student to a hall">
        <form wire:submit="assign" class="flex flex-wrap items-end gap-3">
            <label class="erp-field-label">Roll number<input type="text" wire:model="roll" class="erp-field" required /></label>
            <label class="erp-field-label">Hall<select wire:model="hallId" class="erp-field" required><option value="">Choose…</option>@foreach ($this->halls() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
            <label class="erp-field-label">Room<input type="text" wire:model="room" class="erp-field" /></label>
            <x-filament::button type="submit">Assign</x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section heading="Dues">
        <form wire:submit="addDue" class="flex flex-wrap items-end gap-3">
            <label class="erp-field-label">Roll number<input type="text" wire:model="dueRoll" class="erp-field" required /></label>
            <label class="erp-field-label">Description<input type="text" wire:model="dueDescription" class="erp-field" required /></label>
            <label class="erp-field-label">Amount<input type="number" step="0.01" min="0.01" wire:model="dueAmount" class="erp-field" required /></label>
            <x-filament::button type="submit">Record due</x-filament::button>
        </form>
        <ul class="mt-4 space-y-1 text-sm">
            @forelse ($this->openDues() as $due)
                <li class="flex justify-between"><span>{{ $due->student->user->name }} — {{ $due->description }} ({{ number_format($due->amount, 2) }})</span><x-filament::button size="xs" wire:click="settle({{ $due->id }})">Mark settled</x-filament::button></li>
            @empty
                <li class="text-gray-500">No open dues.</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-panels::page>
