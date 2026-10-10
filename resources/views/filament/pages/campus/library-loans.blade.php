<x-filament-panels::page>
    <x-filament::section heading="Issue a book">
        <form wire:submit="issue" class="flex flex-wrap items-end gap-3">
            <label class="erp-field-label">Roll number<input type="text" wire:model="roll" class="erp-field" required /></label>
            <label class="erp-field-label">Book title<input type="text" wire:model="bookTitle" class="erp-field" required /></label>
            <label class="erp-field-label">Due on<input type="date" wire:model="dueOn" class="erp-field" /></label>
            <x-filament::button type="submit">Issue</x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section heading="Open loans and unpaid fines">
        <table class="w-full text-left text-sm">
            <thead><tr><th class="py-2" scope="col">Student</th><th scope="col">Book</th><th scope="col">Due</th><th scope="col">Fine</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            @forelse ($this->openLoans() as $loan)
                <tr class="border-t border-gray-100 dark:border-white/5" wire:key="loan-{{ $loan->id }}">
                    <td class="py-2">{{ $loan->student->user->name }}</td><td>{{ $loan->book_title }}</td><td>{{ $loan->due_on->format('d M Y') }}</td>
                    <td>{{ number_format($loan->fine_amount, 2) }}</td>
                    <td class="space-x-2">
                        @unless ($loan->returned_on)<x-filament::button size="xs" wire:click="returned({{ $loan->id }})">Mark returned</x-filament::button>@endunless
                        @if ($loan->hasUnpaidFine())<x-filament::button size="xs" color="gray" wire:click="settleFine({{ $loan->id }})">Fine paid</x-filament::button>@endif
                    </td>
                </tr>
            @empty
                <tr><td class="py-2 text-gray-500" colspan="5">No open loans.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
