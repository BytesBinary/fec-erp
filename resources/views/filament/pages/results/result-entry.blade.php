<x-filament-panels::page>
    <x-filament::section>
        <label class="block text-sm font-medium" for="offering">Course offering</label>
        <select id="offering" wire:model.live="offeringId" class="fi-input mt-1 block w-full max-w-xl rounded-lg border-gray-300 text-sm">
            <option value="">Choose…</option>
            @foreach ($this->offerings() as $offering)
                <option value="{{ $offering->id }}">{{ $offering->semester->name }} — {{ $offering->course->code }} {{ $offering->course->name }} ({{ $offering->section }})</option>
            @endforeach
        </select>
    </x-filament::section>

    @if ($offeringId)
        <x-filament::section heading="Students">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="py-2" scope="col">Student</th><th scope="col">Marks</th><th scope="col">Grade</th><th scope="col">Status</th></tr></thead>
                <tbody>
                @foreach ($this->roster() as $enrollment)
                    <tr class="border-t border-gray-100 dark:border-white/5" wire:key="enr-{{ $enrollment->id }}">
                        <td class="py-2">{{ $enrollment->student->user->name }} <span class="text-xs text-gray-500">{{ $enrollment->student->roll_number }}</span></td>
                        <td><input type="number" step="0.01" min="0" max="100" class="w-24 rounded-lg border-gray-300 text-sm" wire:model="marks.{{ $enrollment->id }}" aria-label="Marks for {{ $enrollment->student->user->name }}" @disabled($enrollment->result && $enrollment->result->status !== \App\Enums\ResultStatus::Draft) /></td>
                        <td>{{ $enrollment->result?->letter }}</td>
                        <td>{{ $enrollment->result?->status->label() ?? 'No marks' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-filament::button wire:click="saveMarks">Save marks</x-filament::button>
                {{ $this->submitAction }}
                {{ $this->approveAction }}
                {{ $this->publishAction }}
            </div>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
