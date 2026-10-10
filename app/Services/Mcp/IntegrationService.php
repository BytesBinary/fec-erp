<?php

namespace App\Services\Mcp;

use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\ValidationException;
use App\Exceptions\Mcp\McpAuthenticationException;
use App\Models\AuditLog;
use App\Models\McpIntegration;
use App\Models\User;
use App\Notifications\McpNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Security\TwoFactorResult;
use App\Services\Security\TwoFactorService;
use App\Support\Authorization\Authorizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AI integrations (spec §4.1, §4.5): create (2FA + step-up), authenticate a
 * bearer token on every request, revoke, rename and oversee.
 */
class IntegrationService
{
    public function __construct(
        protected Authorizer $authorizer,
        protected AuditLogger $audit,
        protected TwoFactorService $twoFactor,
        protected McpSettingsService $settings,
    ) {}

    /**
     * Creates an integration. Needs 2FA on the account, a current authenticator
     * code (step-up, every time) and a free slot.
     *
     * @return array{integration: McpIntegration, token: string}
     */
    public function create(User $user, string $name, string $clientType, string $accessLevel, ?int $expiresInDays, string $totpCode): array
    {
        $this->assertMayCreate($user);

        if (trim($name) === '' || mb_strlen($name) > 120) {
            throw new ValidationException('Give the integration a name (up to 120 characters).');
        }

        if (! in_array($clientType, McpIntegration::CLIENTS, true)) {
            throw new ValidationException('Unknown AI client type.');
        }

        if (! in_array($accessLevel, ['full', 'read_only'], true)) {
            throw new ValidationException('Access level must be "full" or "read_only".');
        }

        if ($expiresInDays === null && ! $this->settings->settings()->allow_never_expire) {
            throw new ValidationException('Integrations must expire. Choose 30, 90, 180 or 365 days.');
        }

        if ($expiresInDays !== null && ! in_array($expiresInDays, config('mcp_access.expiry_options_days'), true)) {
            throw new ValidationException('Expiry must be one of: '.implode(', ', config('mcp_access.expiry_options_days')).' days.');
        }

        if ($this->twoFactor->verify($user, $totpCode) !== TwoFactorResult::Valid) {
            throw new ValidationException('Enter the current 6-digit code from your authenticator app.');
        }

        $plain = config('mcp_access.token_prefix').Str::random(48);

        $integration = $this->audit->as($user, fn (): McpIntegration => DB::transaction(function () use ($user, $name, $clientType, $accessLevel, $expiresInDays, $plain): McpIntegration {
            $this->assertSlotAvailable($user);

            return McpIntegration::query()->create([
                'user_id' => $user->getKey(),
                'name' => $name,
                'client_type' => $clientType,
                'access_level' => $accessLevel,
                'token_hash' => $this->hash($plain),
                'token_prefix' => substr($plain, 0, 12),
                'expires_at' => $expiresInDays === null ? null : now()->addDays($expiresInDays),
            ]);
        }));

        $this->audit->record('mcp.integration_created', $integration, null, ['name' => $name, 'client' => $clientType, 'access' => $accessLevel], $user);
        $user->notify(new McpNotification(__('erp.mcp.created_title'), __('erp.mcp.created_body', ['name' => $name]), 'info', 'mcp.integration_created'));

        return ['integration' => $integration, 'token' => $plain];
    }

    /**
     * Resolves a bearer token to its integration. Runs on EVERY MCP request.
     *
     * @throws McpAuthenticationException
     */
    public function authenticate(?string $token, ?string $ip = null): McpIntegration
    {
        if ($token === null || $token === '') {
            throw new McpAuthenticationException('TOKEN_MISSING', 'Send the integration token as "Authorization: Bearer <token>".');
        }

        $integration = McpIntegration::query()->with('user')->where('token_hash', $this->hash($token))->first();

        if ($integration === null) {
            throw new McpAuthenticationException('TOKEN_INVALID', 'This token is not recognised.');
        }

        $this->assertUsable($integration);

        $this->recordUse($integration, $ip);

        return $integration;
    }

    /**
     * Re-checks an already authenticated integration against the database.
     * Long-lived transports (stdio) call this before every request so a
     * revoked, expired or 2FA-less integration stops at once.
     *
     * @throws McpAuthenticationException
     */
    public function revalidate(McpIntegration $integration): McpIntegration
    {
        $fresh = McpIntegration::query()->with('user')->find($integration->getKey())
            ?? throw new McpAuthenticationException('TOKEN_INVALID', 'This token is not recognised.');

        $this->assertUsable($fresh);

        return $fresh;
    }

    /**
     * @throws McpAuthenticationException
     */
    protected function assertUsable(McpIntegration $integration): void
    {
        if ($integration->isRevoked()) {
            throw new McpAuthenticationException('TOKEN_REVOKED', 'This integration was stopped. Create a new one in Settings → AI Integrations.');
        }

        if ($integration->isExpired()) {
            throw new McpAuthenticationException('TOKEN_EXPIRED', 'This integration expired. Create a new one in Settings → AI Integrations.');
        }

        $user = $integration->user;

        if ($user === null || $user->is_active === false || $user->trashed()) {
            throw new McpAuthenticationException('ACCOUNT_INACTIVE', 'The account behind this integration is not active.');
        }

        if (! $this->settings->enabledForUser($user)) {
            throw new McpAuthenticationException('MCP_DISABLED', 'AI integrations are switched off for your role.');
        }

        if (! $this->twoFactor->isEnabled($user)) {
            throw new McpAuthenticationException('MFA_REQUIRED', 'Two-factor authentication must be enabled to use AI integrations.');
        }
    }

    /**
     * Stops one integration. Owner or an admin with `mcp_integration:manage_all`.
     */
    public function revoke(User $actor, McpIntegration $integration, string $reason = 'Stopped by user'): McpIntegration
    {
        $this->assertOwnerOrAdmin($actor, $integration);

        if ($integration->isRevoked()) {
            throw new ConflictException('This integration is already stopped.');
        }

        $byAdmin = $integration->user_id !== $actor->getKey();

        if ($byAdmin && trim($reason) === '') {
            throw new ValidationException('A reason is required to revoke someone else\'s integration.');
        }

        $integration->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->getKey(), 'revoked_reason' => $reason])->save();

        $this->audit->record('mcp.integration_revoked', $integration, null, ['reason' => $reason, 'by_admin' => $byAdmin], $actor);

        $integration->user->notify(new McpNotification(
            __('erp.mcp.revoked_title'),
            $byAdmin ? __('erp.mcp.revoked_by_admin_body', ['name' => $integration->name, 'reason' => $reason]) : __('erp.mcp.revoked_body', ['name' => $integration->name]),
            $byAdmin ? 'danger' : 'info',
            'mcp.integration_revoked',
        ));

        return $integration;
    }

    /**
     * Revokes every active integration of `$user` (stop all, 2FA disabled/reset,
     * log out everywhere).
     */
    public function stopAllFor(User $user, ?User $by = null, string $reason = 'Stopped'): int
    {
        $by ??= $user;
        $count = 0;

        McpIntegration::query()->where('user_id', $user->getKey())->whereNull('revoked_at')->each(function (McpIntegration $integration) use ($by, $reason, &$count): void {
            $integration->forceFill(['revoked_at' => now(), 'revoked_by' => $by->getKey(), 'revoked_reason' => $reason])->save();
            $this->audit->record('mcp.integration_revoked', $integration, null, ['reason' => $reason], $by);
            $count++;
        });

        if ($count > 0) {
            $user->notify(new McpNotification(__('erp.mcp.revoked_title'), __('erp.mcp.stopped_all_body', ['count' => $count, 'reason' => $reason]), 'warning', 'mcp.integration_stopped_all'));
        }

        return $count;
    }

    public function rename(User $actor, McpIntegration $integration, string $name): McpIntegration
    {
        if ($integration->user_id !== $actor->getKey()) {
            throw new ForbiddenException(__('erp.errors.forbidden', ['permission' => 'mcp:rename']));
        }

        if (trim($name) === '' || mb_strlen($name) > 120) {
            throw new ValidationException('Give the integration a name (up to 120 characters).');
        }

        $integration->update(['name' => $name]);

        return $integration;
    }

    /**
     * The user's own integrations, newest first.
     *
     * @return Collection<int, McpIntegration>
     */
    public function listFor(User $user): Collection
    {
        return McpIntegration::query()->where('user_id', $user->getKey())->orderByDesc('id')->get();
    }

    public function callsInLastDays(McpIntegration $integration, int $days = 7): int
    {
        return AuditLog::query()->where('integration_id', $integration->getKey())->where('action', 'mcp.tool_call')->where('created_at', '>=', now()->subDays($days))->count();
    }

    /**
     * Audit entries of one integration (tool, time, outcome, entity touched).
     *
     * @return Collection<int, AuditLog>
     */
    public function activity(User $actor, McpIntegration $integration, int $limit = 100): Collection
    {
        $this->assertOwnerOrAdmin($actor, $integration);

        return AuditLog::query()->where('integration_id', $integration->getKey())->latest('id')->limit($limit)->get();
    }

    /**
     * Admin overview of everyone's integrations.
     *
     * @param  array<string, mixed>  $filters  user (name/email), role, client, status, active_since
     * @return Builder<McpIntegration>
     */
    public function adminQuery(User $actor, array $filters = []): Builder
    {
        $this->authorizer->authorize($actor, 'mcp_integration:manage_all');

        return McpIntegration::query()->with('user.roles')
            ->when(! empty($filters['user']), fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->where('name', 'like', '%'.$filters['user'].'%')->orWhere('email', 'like', '%'.$filters['user'].'%')))
            ->when(! empty($filters['role']), fn (Builder $query) => $query->whereHas('user.roles', fn (Builder $role) => $role->where('name', $filters['role'])))
            ->when(! empty($filters['client']), fn (Builder $query) => $query->where('client_type', $filters['client']))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->active())
            ->when(($filters['status'] ?? null) === 'revoked', fn (Builder $query) => $query->whereNotNull('revoked_at'))
            ->when(($filters['status'] ?? null) === 'expired', fn (Builder $query) => $query->whereNull('revoked_at')->where('expires_at', '<=', now()))
            ->latest('id');
    }

    /**
     * Usage overview for the admin dashboard.
     *
     * @return array{active: int, calls_per_day: array<string, int>, denied_per_day: array<string, int>}
     */
    public function usageOverview(User $actor, int $days = 7): array
    {
        $this->authorizer->authorize($actor, 'mcp_integration:manage_all');

        $logs = AuditLog::query()->where('action', 'mcp.tool_call')->where('created_at', '>=', now()->subDays($days)->startOfDay())->get(['created_at', 'after']);

        $calls = [];
        $denied = [];

        foreach (range($days - 1, 0) as $ago) {
            $day = now()->subDays($ago)->toDateString();
            $calls[$day] = 0;
            $denied[$day] = 0;
        }

        foreach ($logs as $log) {
            $day = $log->created_at->toDateString();
            $calls[$day] = ($calls[$day] ?? 0) + 1;

            if (($log->after['outcome'] ?? null) === 'denied') {
                $denied[$day] = ($denied[$day] ?? 0) + 1;
            }
        }

        return ['active' => McpIntegration::query()->active()->count(), 'calls_per_day' => $calls, 'denied_per_day' => $denied];
    }

    /**
     * Integrations expiring within the warning window that were not yet notified.
     */
    public function notifyExpiring(): int
    {
        $count = 0;

        McpIntegration::query()->active()->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays((int) config('mcp_access.expiring_soon_days')))
            ->whereNull('expiry_notified_at')
            ->with('user')
            ->each(function (McpIntegration $integration) use (&$count): void {
                $integration->user->notify(new McpNotification(__('erp.mcp.expiring_title'), __('erp.mcp.expiring_body', ['name' => $integration->name, 'date' => $integration->expires_at->format('d M Y')]), 'warning', 'mcp.integration_expiring'));
                $integration->forceFill(['expiry_notified_at' => now()])->save();
                $count++;
            });

        return $count;
    }

    public function assertMayCreate(User $user): void
    {
        if (! $this->settings->enabledForUser($user)) {
            throw new ForbiddenException(__('erp.mcp.disabled_for_you'));
        }

        if (! $this->twoFactor->isEnabled($user)) {
            throw new InvalidStateException(__('erp.mcp.needs_2fa'), ['code' => 'MFA_REQUIRED']);
        }

        $this->assertSlotAvailable($user);
    }

    public function activeCount(User $user): int
    {
        return McpIntegration::query()->where('user_id', $user->getKey())->active()->count();
    }

    protected function assertSlotAvailable(User $user): void
    {
        $max = $this->settings->settings()->max_integrations_per_user;

        if ($this->activeCount($user) >= $max) {
            throw new ConflictException(__('erp.mcp.limit_reached', ['max' => $max]));
        }
    }

    protected function assertOwnerOrAdmin(User $actor, McpIntegration $integration): void
    {
        if ($integration->user_id === $actor->getKey()) {
            return;
        }

        $this->authorizer->authorize($actor, 'mcp_integration:manage_all');
    }

    protected function recordUse(McpIntegration $integration, ?string $ip): void
    {
        $known = $integration->known_ips ?? [];
        $first = $integration->first_connected_at === null;
        $newIp = $ip !== null && ! in_array($ip, $known, true);

        if ($newIp && ! $first) {
            $integration->user->notify(new McpNotification(__('erp.mcp.new_ip_title'), __('erp.mcp.new_ip_body', ['name' => $integration->name, 'ip' => $ip]), 'warning', 'mcp.new_ip'));
        }

        $integration->forceFill([
            'first_connected_at' => $integration->first_connected_at ?? now(),
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'known_ips' => $newIp ? array_slice([...$known, $ip], -20) : $known,
        ])->save();
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
