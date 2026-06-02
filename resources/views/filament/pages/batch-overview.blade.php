<x-filament::page>
    <div class="space-y-4">
        @if(empty($this->batches))
            <div class="p-8 text-center text-gray-500">
                No active batches found. Click "Add New Batch" to get started.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Batch No.</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Session</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Departments</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->batches as $batch)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                <td class="px-4 py-3">
                                    <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                                        Batch {{ $batch['batch_number'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    {{ $batch['session'] }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-filament::badge color="gray">
                                        {{ $batch['dept_count'] }} {{ Str::plural('department', $batch['dept_count']) }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-filament::link
                                        href="{{ \App\Filament\Pages\BatchDetail::getUrl(['batchNumber' => $batch['batch_number']]) }}"
                                        icon="heroicon-o-pencil-square"
                                    >
                                        Manage
                                    </x-filament::link>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament::page>
