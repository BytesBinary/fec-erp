<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap gap-4">
            <div>
                <label class="block text-sm font-medium" for="desk-search">Student ID, name or request no.</label>
                <input id="desk-search" type="search" wire:model.live.debounce.300ms="search" class="erp-field" />
            </div>
            <div>
                <label class="block text-sm font-medium" for="desk-department">Department</label>
                <select id="desk-department" wire:model.live="departmentId" class="erp-field">
                    <option value="">All</option>
                    @foreach ($this->departments() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium" for="desk-session">Session</label>
                <select id="desk-session" wire:model.live="session" class="erp-field">
                    <option value="">All</option>
                    @foreach ($this->sessions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium" for="desk-status">Status</label>
                <select id="desk-status" wire:model.live="status" class="erp-field">
                    @foreach ($this->statuses() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
        </div>
    </x-filament::section>

    @php($requests = $this->requests())
    <x-filament::section :heading="$requests->count().' found'">
        @if ($requests->isEmpty())
            <p class="text-sm text-gray-500" data-testid="desk-empty">No clearance requests match.</p>
        @else
            <table class="w-full text-left text-sm" data-testid="desk-table">
                <thead><tr><th class="py-2" scope="col">Request</th><th scope="col">Student</th><th scope="col">Roll</th><th scope="col">Department</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
                <tbody>
                @foreach ($requests as $request)
                    <tr class="border-t border-gray-100 dark:border-white/5" wire:key="desk-{{ $request->id }}" data-request="{{ $request->request_no }}">
                        <td class="py-2">{{ $request->request_no }}</td>
                        <td>{{ $request->student->user->name }}</td>
                        <td>{{ $request->student->roll_number }}</td>
                        <td>{{ $request->student->department?->code }}</td>
                        <td>{{ $request->status->label() }}</td>
                        <td><a class="text-primary-700 underline" href="{{ \App\Filament\Pages\Clearance\ViewClearance::getUrl(['record' => $request->id]) }}">Open</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
