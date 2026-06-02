<x-filament::page>
    <div class="space-y-4">
        @if(empty($this->rows))
            <div class="p-8 text-center text-gray-500">
                No departments assigned to Batch {{ $this->batchNumber }} yet.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Department</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Session</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Current Semester</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Status</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->rows as $row)
                            <tr class="{{ $row['is_archived'] ? 'opacity-50' : '' }} hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $row['department_name'] }}
                                    @if($row['is_archived'])
                                        <x-filament::badge color="warning" class="ml-2">Archived</x-filament::badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $row['session'] }}</td>
                                <td class="px-4 py-3">
                                    <select
                                        wire:change="updateSemester({{ $row['id'] }}, $event.target.value)"
                                        class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm"
                                        @if($row['is_archived']) disabled @endif
                                    >
                                        @foreach(range(1, 8) as $sem)
                                            <option value="{{ $sem }}" @selected($row['current_semester'] === $sem)>
                                                Semester {{ $sem }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    @if($row['is_active'])
                                        <x-filament::badge color="success">Active</x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray">Inactive</x-filament::badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    @if(!$row['is_archived'])
                                        <button
                                            wire:click="toggleActive({{ $row['id'] }})"
                                            class="text-xs text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline"
                                        >
                                            {{ $row['is_active'] ? 'Deactivate' : 'Activate' }}
                                        </button>

                                        {{ ($this->archiveBatchAction)(['batchId' => $row['id']]) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament::page>
