<x-filament-panels::page>
    @php($described = $this->described())

    <x-filament::section>
        <div class="space-y-2" data-testid="profile-progress">
            <div class="flex justify-between text-sm">
                <span>{{ __('erp.profile.progress', ['done' => $described['progress']['done'], 'total' => $described['progress']['total']]) }}</span>
                <span>{{ $described['progress']['percent'] }}%</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10" role="progressbar" aria-label="Profile completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $described['progress']['percent'] }}">
                <div class="h-2 rounded-full bg-primary-600" style="width: {{ $described['progress']['percent'] }}%"></div>
            </div>
            @if ($described['problems'] !== [])
                <ul class="list-disc pl-5 text-sm text-warning-600" data-testid="profile-problems">
                    @foreach ($described['problems'] as $problem)
                        <li>{{ $problem }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::actions :actions="$this->getFormActions()" />
    </form>
</x-filament-panels::page>
