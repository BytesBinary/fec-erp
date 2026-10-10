<x-filament-panels::page>
    @php($usage = $this->usage())

    <x-filament::section heading="Switches">
        <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3">
                <span>AI integrations (MCP) are <strong data-testid="global-state">{{ $this->globalEnabled() ? 'ON' : 'OFF' }}</strong> for everyone</span>
                <x-filament::button size="sm" :color="$this->globalEnabled() ? 'danger' : 'success'" wire:click="toggleGlobal" id="toggle-global">{{ $this->globalEnabled() ? 'Switch off' : 'Switch on' }}</x-filament::button>
            </div>
            <div class="flex items-center justify-between gap-3">
                <span>Allow integrations that never expire</span>
                <x-filament::button size="sm" color="gray" wire:click="toggleNeverExpire">{{ app(\App\Services\Mcp\McpSettingsService::class)->settings()->allow_never_expire ? 'Allowed — click to forbid' : 'Forbidden — click to allow' }}</x-filament::button>
            </div>
            <div>
                <div class="mb-1 font-medium">Roles allowed to use MCP</div>
                <ul class="grid gap-2 sm:grid-cols-2">
                    @foreach ($this->roles() as $role)
                        <li class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10" wire:key="role-{{ $role->id }}" data-role="{{ $role->name }}">
                            <span>{{ \App\Enums\RoleKey::tryFrom($role->name)?->label() ?? $role->name }}</span>
                            <x-filament::button size="xs" :color="$this->roleEnabled($role) ? 'success' : 'gray'" wire:click="toggleRole({{ $role->id }})">{{ $this->roleEnabled($role) ? 'Allowed' : 'Blocked' }}</x-filament::button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Usage (last 7 days)">
        <p class="mb-2 text-sm">Active integrations: <strong data-testid="active-count">{{ $usage['active'] }}</strong></p>
        <table class="w-full text-left text-sm">
            <thead><tr><th class="py-1" scope="col">Day</th><th scope="col">Calls</th><th scope="col">Denied</th></tr></thead>
            <tbody>
            @foreach ($usage['calls_per_day'] as $day => $count)
                <tr class="border-t border-gray-100 dark:border-white/5"><td class="py-1">{{ $day }}</td><td>{{ $count }}</td><td>{{ $usage['denied_per_day'][$day] ?? 0 }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="All integrations">
        <div class="mb-4 flex flex-wrap gap-3">
            <input type="search" wire:model.live.debounce.300ms="userFilter" placeholder="User name or email" aria-label="Filter by user" class="rounded-lg border-gray-300 text-sm" id="filter-user" />
            <select wire:model.live="roleFilter" aria-label="Filter by role" class="rounded-lg border-gray-300 text-sm"><option value="">All roles</option>@foreach ($this->roles() as $role)<option value="{{ $role->name }}">{{ $role->name }}</option>@endforeach</select>
            <select wire:model.live="clientFilter" aria-label="Filter by client" class="rounded-lg border-gray-300 text-sm"><option value="">All clients</option>@foreach ($this->clients() as $key => $client)<option value="{{ $key }}">{{ $client['label'] }}</option>@endforeach</select>
            <select wire:model.live="statusFilter" aria-label="Filter by status" class="rounded-lg border-gray-300 text-sm"><option value="">Any status</option><option value="active">Active</option><option value="revoked">Revoked</option><option value="expired">Expired</option></select>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" data-testid="all-integrations">
                <thead><tr><th class="py-2 pr-3" scope="col">User</th><th class="pr-3" scope="col">Roles</th><th class="pr-3" scope="col">Integration</th><th class="pr-3" scope="col">Client</th><th class="pr-3" scope="col">Access</th><th class="pr-3" scope="col">Last used</th><th class="pr-3" scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @forelse ($this->integrations() as $integration)
                    <tr class="border-t border-gray-100 dark:border-white/5" wire:key="all-{{ $integration->id }}" data-integration="{{ $integration->name }}" data-owner="{{ $integration->user->email }}" data-status="{{ $integration->status() }}">
                        <td class="py-2 pr-3">{{ $integration->user->name }}<div class="text-xs text-gray-500">{{ $integration->user->email }}</div></td>
                        <td class="pr-3">{{ $integration->user->roles->pluck('name')->implode(', ') }}</td>
                        <td class="pr-3">{{ $integration->name }}</td>
                        <td class="pr-3">{{ app(\App\Services\Mcp\ClientSnippets::class)->label($integration->client_type) }}</td>
                        <td class="pr-3">{{ $integration->access_level === 'read_only' ? 'Read-only' : 'Full' }}</td>
                        <td class="pr-3">{{ $integration->last_used_at?->diffForHumans() ?? '—' }}</td>
                        <td class="pr-3">{{ __('erp.mcp.statuses.'.$integration->status()) }}@if ($integration->revoked_reason)<div class="text-xs text-gray-500">{{ $integration->revoked_reason }}</div>@endif</td>
                        <td>@unless ($integration->isRevoked()){{ ($this->revokeAction)(['integration' => $integration->id]) }}@endunless</td>
                    </tr>
                @empty
                    <tr><td class="py-2 text-gray-500" colspan="8">No integrations match.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
