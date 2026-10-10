<?php

use App\Models\McpIntegration;
use App\Services\Security\TwoFactorResult;
use App\Services\Security\TwoFactorService;
use Database\Seeders\Testing\TestDataset as T;
use Tests\Mcp\McpClient;

beforeEach(function () {
    seedTestDataset();
});

it('seeds a user whose 2FA is already enabled with the fixed test secret', function () {
    $user = datasetUser(T::MFA_USER);

    expect(app(TwoFactorService::class)->isEnabled($user))->toBeTrue()
        ->and(app(TwoFactorService::class)->verify($user, totpCode(T::MFA_SECRET)))->toBe(TwoFactorResult::Valid);
});

it('seeds a user with an active MCP integration whose fixed token works', function () {
    $user = datasetUser(T::MCP_USER);

    expect(McpIntegration::query()->where('user_id', $user->id)->active()->count())->toBe(1);

    $client = new McpClient($this, T::MCP_TOKEN);

    expect($client->call('me_get_profile')['payload']['data']['email'])->toBe(T::MCP_USER);
});

it('is idempotent', function () {
    seedTestDataset();

    expect(McpIntegration::query()->count())->toBe(1);
});
