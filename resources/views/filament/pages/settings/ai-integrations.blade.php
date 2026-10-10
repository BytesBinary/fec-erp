<x-filament-panels::page>
    @unless ($this->hasTwoFactor())
        <x-filament::section>
            <div class="space-y-3" data-testid="mfa-gate">
                <p class="text-sm">{{ __('erp.mcp.needs_2fa') }}</p>
                <x-filament::button tag="a" :href="$this->setupTwoFactorUrl()" icon="heroicon-o-shield-check">{{ __('erp.mcp.setup_2fa') }}</x-filament::button>
            </div>
        </x-filament::section>
    @else
        <x-filament::section :heading="__('erp.mcp.your_integrations')" :description="__('erp.mcp.slots', ['active' => $this->activeCount(), 'max' => $this->maxIntegrations()])">
            <x-slot name="afterHeader">
                <div class="flex gap-2">
                    {{ $this->stopAllAction }}
                    <x-filament::button wire:click="startWizard" id="start-wizard" icon="heroicon-o-plus">{{ __('erp.mcp.connect') }}</x-filament::button>
                </div>
            </x-slot>

            @php($integrations = $this->integrations())
            @if ($integrations->isEmpty())
                <p class="text-sm text-gray-500" data-testid="no-integrations">{{ __('erp.mcp.none') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" data-testid="integrations-table">
                        <thead><tr>
                            <th class="py-2 pr-3" scope="col">Name</th><th class="pr-3" scope="col">Client</th><th class="pr-3" scope="col">Access</th><th class="pr-3" scope="col">Created</th>
                            <th class="pr-3" scope="col">Last used</th><th class="pr-3" scope="col">Expires</th><th class="pr-3" scope="col">Status</th><th class="pr-3" scope="col">Calls (7 days)</th><th scope="col"><span class="sr-only">Actions</span></th>
                        </tr></thead>
                        <tbody>
                        @foreach ($integrations as $integration)
                            <tr class="border-t border-gray-100 align-top dark:border-white/5" wire:key="int-{{ $integration->id }}" data-integration="{{ $integration->name }}" data-status="{{ $integration->status() }}">
                                <td class="py-2 pr-3 font-medium">{{ $integration->name }}</td>
                                <td class="pr-3">{{ app(\App\Services\Mcp\ClientSnippets::class)->label($integration->client_type) }}</td>
                                <td class="pr-3">{{ $integration->access_level === 'read_only' ? 'Read-only' : 'Full access' }}</td>
                                <td class="pr-3">{{ $integration->created_at->format('d M Y') }}</td>
                                <td class="pr-3" data-testid="last-used">@if ($integration->last_used_at){{ $integration->last_used_at->diffForHumans() }} <span class="text-xs text-gray-500">({{ $integration->last_used_ip }})</span>@else — @endif</td>
                                <td class="pr-3">{{ $integration->expires_at?->format('d M Y') ?? 'Never' }}</td>
                                <td class="pr-3"><x-filament::badge :color="match ($integration->status()) { 'active' => 'success', 'expiring_soon' => 'warning', default => 'gray' }">{{ __('erp.mcp.statuses.'.$integration->status()) }}</x-filament::badge></td>
                                <td class="pr-3" data-testid="call-count">{{ $this->callsLastWeek($integration) }}</td>
                                <td class="space-x-1 whitespace-nowrap">
                                    <x-filament::button size="sm" color="gray" wire:click="showActivity({{ $integration->id }})">{{ __('erp.mcp.activity') }}</x-filament::button>
                                    @if (! $integration->isRevoked())
                                        {{ ($this->renameAction)(['integration' => $integration->id]) }}
                                        {{ ($this->stopAction)(['integration' => $integration->id]) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        @if ($activityIntegrationId)
            <x-filament::section :heading="__('erp.mcp.activity')">
                <x-slot name="afterHeader"><x-filament::button size="xs" color="gray" wire:click="hideActivity">{{ __('erp.mcp.close') }}</x-filament::button></x-slot>
                @include('filament.pages.settings.partials.mcp-activity', ['logs' => $this->activityLogs()])
            </x-filament::section>
        @endif

        @if ($wizardOpen)
            <x-filament::section :heading="__('erp.mcp.wizard_title')" data-testid="wizard">
                <ol class="mb-4 flex flex-wrap gap-2 text-xs" aria-label="Steps">
                    @foreach (['Client', 'Name and limits', 'Confirm with 2FA', 'Connect', 'Test connection'] as $index => $label)
                        <li class="rounded-full px-3 py-1 {{ $step === $index + 1 ? 'bg-primary-800 text-white' : 'bg-gray-100 text-gray-700' }}" @if ($step === $index + 1) aria-current="step" @endif>{{ $index + 1 }}. {{ $label }}</li>
                    @endforeach
                </ol>

                @if ($wizardError)
                    <p class="mb-3 rounded-lg bg-danger-50 p-3 text-sm text-danger-800" role="alert" data-testid="wizard-error">{{ $wizardError }}</p>
                @endif

                @if ($step === 1)
                    <fieldset class="space-y-2">
                        <legend class="mb-2 text-sm font-medium">{{ __('erp.mcp.choose_client') }}</legend>
                        @foreach ($this->clients() as $key => $client)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 {{ $clientType === $key ? 'border-primary-600' : 'border-gray-200' }}">
                                <input type="radio" name="client" value="{{ $key }}" wire:click="chooseClient('{{ $key }}')" @checked($clientType === $key) class="mt-1" />
                                <span><span class="block font-medium">{{ $client['label'] }}</span><span class="text-sm text-gray-500">{{ $client['description'] }}</span></span>
                            </label>
                        @endforeach
                    </fieldset>
                @elseif ($step === 2)
                    <div class="space-y-4">
                        <label class="block text-sm font-medium">{{ __('erp.mcp.name') }}
                            <input id="integration-name" type="text" wire:model="integrationName" maxlength="120" class="mt-1 block w-full max-w-md rounded-lg border-gray-300 text-sm" />
                        </label>
                        <fieldset>
                            <legend class="text-sm font-medium">{{ __('erp.mcp.access') }}</legend>
                            <label class="mt-1 flex items-center gap-2 text-sm"><input type="radio" wire:model="accessLevel" value="full" /> {{ __('erp.mcp.access_full') }}</label>
                            <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model="accessLevel" value="read_only" id="access-read-only" /> {{ __('erp.mcp.access_read_only') }}</label>
                        </fieldset>
                        <label class="block text-sm font-medium">{{ __('erp.mcp.expiry') }}
                            <select id="expiry" wire:model="expiresInDays" class="mt-1 block rounded-lg border-gray-300 text-sm">
                                @foreach ($this->expiryOptions() as $days => $label)<option value="{{ $days }}">{{ $label }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                @elseif ($step === 3)
                    <div class="space-y-3">
                        <p class="text-sm">{{ __('erp.mcp.confirm_help') }}</p>
                        <label class="block text-sm font-medium">{{ __('erp.mcp.code') }}
                            <input id="totp-code" type="text" inputmode="numeric" autocomplete="one-time-code" wire:model="totpCode" class="mt-1 block w-48 rounded-lg border-gray-300 text-sm" />
                        </label>
                    </div>
                @elseif ($step === 4)
                    @php($snippet = $this->snippet())
                    <div class="space-y-4" x-data="{ copied: false }">
                        <p class="rounded-lg bg-warning-50 p-3 text-sm text-warning-800">{{ __('erp.mcp.token_once') }}</p>
                        <div>
                            <div class="text-sm font-medium">{{ __('erp.mcp.your_token') }}</div>
                            <div class="mt-1 flex items-center gap-2">
                                <code id="new-token" class="block flex-1 break-all rounded-lg bg-gray-100 p-2 text-sm dark:bg-white/10" data-testid="new-token">{{ $newToken }}</code>
                                <x-filament::button type="button" color="gray" x-on:click="navigator.clipboard.writeText(@js($newToken)); copied = true">
                                    <span x-show="!copied">{{ __('erp.mcp.copy') }}</span><span x-show="copied" x-cloak>{{ __('erp.mcp.copied') }}</span>
                                </x-filament::button>
                            </div>
                        </div>
                        @if ($snippet)
                            <div>
                                <div class="text-sm font-medium">{{ $snippet['label'] }} — {{ $snippet['file'] }}</div>
                                <pre class="mt-1 overflow-x-auto rounded-lg bg-gray-900 p-3 text-xs text-gray-100" data-testid="client-snippet"><code>{{ $snippet['snippet'] }}</code></pre>
                            </div>
                            <ol class="list-decimal space-y-1 pl-5 text-sm">
                                @foreach ($snippet['steps'] as $stepText)<li>{{ $stepText }}</li>@endforeach
                            </ol>
                        @endif
                    </div>
                @else
                    <div class="space-y-3" wire:poll.2s="checkConnection">
                        @if ($connected)
                            <p class="rounded-lg bg-success-50 p-3 text-sm font-medium text-success-800" data-testid="connected">Connected ✓ — {{ app(\App\Services\Mcp\ClientSnippets::class)->label($clientType) }} reached the server.</p>
                        @else
                            <p class="text-sm" data-testid="waiting">{{ __('erp.mcp.waiting') }}</p>
                            @if ($this->troubleshooting())
                                <ul class="list-disc space-y-1 pl-5 text-sm text-warning-800" data-testid="troubleshooting">
                                    <li>{{ __('erp.mcp.tip_restart') }}</li><li>{{ __('erp.mcp.tip_url') }}</li><li>{{ __('erp.mcp.tip_token') }}</li>
                                </ul>
                            @endif
                        @endif
                    </div>
                @endif

                <div class="mt-5 flex gap-3">
                    @if ($step > 1 && $step < 4)<x-filament::button color="gray" wire:click="back">{{ __('erp.mcp.back') }}</x-filament::button>@endif
                    @if ($step < 5)
                        <x-filament::button wire:click="next" id="wizard-next">{{ $step === 3 ? __('erp.mcp.create') : ($step === 4 ? __('erp.mcp.i_copied') : __('erp.mcp.next')) }}</x-filament::button>
                    @endif
                    <x-filament::button color="gray" wire:click="closeWizard" id="wizard-close">{{ $step === 5 ? __('erp.mcp.done') : __('erp.mcp.cancel') }}</x-filament::button>
                </div>
            </x-filament::section>
        @endif
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
