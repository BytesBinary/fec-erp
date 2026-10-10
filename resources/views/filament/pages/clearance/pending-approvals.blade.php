<x-filament-panels::page>
    @unless ($this->hasSignature())
        <x-filament::section>
            <p class="text-sm text-warning-700" data-testid="signature-warning">
                You cannot approve until you upload your signature image.
                <a class="underline" href="{{ \App\Filament\Pages\Settings\MySignature::getUrl() }}">Upload it here</a>.
            </p>
        </x-filament::section>
    @endunless

    <x-filament::section>
        <div class="flex flex-wrap gap-4">
            <div>
                <label class="block text-sm font-medium" for="search">Search</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search" class="mt-1 rounded-lg border-gray-300 text-sm" placeholder="Name, roll or request no." />
            </div>
            <div>
                <label class="block text-sm font-medium" for="department">Department</label>
                <select id="department" wire:model.live="departmentId" class="mt-1 rounded-lg border-gray-300 text-sm">
                    <option value="">All</option>
                    @foreach ($this->departments() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filament::section>

    @php($requests = $this->requests())
    <x-filament::section :heading="$requests->count().' waiting'">
        @if ($requests->isEmpty())
            <p class="text-sm text-gray-500" data-testid="no-pending">Nothing is waiting for you.</p>
        @else
            <table class="w-full text-left text-sm" data-testid="pending-table">
                <thead><tr><th class="py-2" scope="col">Request</th><th scope="col">Student</th><th scope="col">Department</th><th scope="col">Stage</th><th scope="col">Submitted</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
                <tbody>
                @foreach ($requests as $request)
                    <tr class="border-t border-gray-100 dark:border-white/5" wire:key="req-{{ $request->id }}" data-request="{{ $request->request_no }}">
                        <td class="py-2">{{ $request->request_no }}</td>
                        <td>{{ $request->student->user->name }} <span class="text-xs text-gray-500">{{ $request->student->roll_number }}</span></td>
                        <td>{{ $request->student->department?->code }}</td>
                        <td>{{ $request->currentStage?->label }}</td>
                        <td>{{ $request->submitted_at?->diffForHumans() }}</td>
                        <td><a class="text-primary-600 underline" href="{{ \App\Filament\Pages\Clearance\ViewClearance::getUrl(['record' => $request->id]) }}">Review</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
