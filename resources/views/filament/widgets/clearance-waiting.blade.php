<x-filament-widgets::widget>
    <x-filament::section>
        <a href="{{ \App\Filament\Pages\Clearance\PendingApprovals::getUrl() }}" class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-600 dark:text-gray-300">Clearance requests waiting for me</span>
            <span class="text-2xl font-semibold" data-testid="waiting-count">{{ $this->count() }}</span>
        </a>
    </x-filament::section>
</x-filament-widgets::widget>
