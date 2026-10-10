<?php

use Database\Seeders\Testing\TestDataset as T;
use Tests\Mcp\McpClient;

beforeEach(function () {
    seedTestDataset();
});

it('lists tools for a super admin token over the real http route', function () {
    ['token' => $token] = mcpIntegrationFor(datasetUser(T::SUPER_ADMIN));
    $client = new McpClient($this, $token);

    $names = $client->toolNames();

    expect(count($names))->toBeGreaterThan(80)->and($names)->toContain('course_create', 'user_list', 'clearance_search');
});

it('calls a tool', function () {
    ['token' => $token] = mcpIntegrationFor(datasetUser(T::SUPER_ADMIN));
    $client = new McpClient($this, $token);

    $result = $client->call('department_list');

    expect($result['isError'])->toBeFalse()
        ->and($result['payload']['summary'])->toContain('departments returned')
        ->and(collect($result['payload']['data']['items'])->pluck('code')->all())->toContain('CSE');
});
