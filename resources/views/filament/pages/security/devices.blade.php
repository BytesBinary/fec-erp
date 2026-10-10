<x-filament-panels::page>
    @php
        $sessions = $this->getSessions();
        $currentHash = $this->currentSessionHash();
    @endphp

    <x-filament::section :heading="__('erp.security.devices_heading')" :description="__('erp.security.devices_description')">
        <ul class="divide-y divide-gray-200 dark:divide-white/10" data-testid="device-list">
            @foreach ($sessions as $session)
                @php($isCurrent = $session->session_hash === $currentHash)
                <li class="flex flex-wrap items-center justify-between gap-3 py-4" data-testid="device-row" wire:key="session-{{ $session->id }}">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $session->device_label }}</span>
                            @if ($isCurrent)
                                <x-filament::badge color="success">{{ __('erp.security.this_device') }}</x-filament::badge>
                            @endif
                            @if ($session->isTrusted())
                                <x-filament::badge color="info">{{ __('erp.security.trusted_device') }}</x-filament::badge>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('erp.security.device_meta', ['ip' => $session->ip ?? '—', 'when' => $session->last_active_at?->diffForHumans() ?? '—']) }}
                            @if ($session->location)
                                · {{ $session->location }}
                            @endif
                        </p>
                    </div>
                    @unless ($isCurrent)
                        {{ ($this->logoutDeviceAction)(['session' => $session->id]) }}
                    @endunless
                </li>
            @endforeach
        </ul>
    </x-filament::section>

    @if ($sessions->count() > 1)
        <x-filament::section :heading="__('erp.security.logout_others_heading')">
            {{ $this->logoutOthersAction }}
        </x-filament::section>
    @endif
    <x-filament-actions::modals />
</x-filament-panels::page>
