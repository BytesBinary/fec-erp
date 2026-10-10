<?php

use App\Models\Department;
use Database\Seeders\Testing\TestDataset as T;
use Tests\Mcp\McpClient;

beforeEach(function () {
    seedTestDataset();
});

it('E2E 10: a course created through MCP with a super admin token appears in the web course list', function () {
    ['token' => $token] = mcpIntegrationFor(datasetUser(T::SUPER_ADMIN), 'full', 90, 'claude_desktop');
    $client = new McpClient($this, $token);
    $department = Department::query()->where('code', T::DEPT_CSE)->firstOrFail();

    $created = $client->call('course_create', [
        'department_id' => $department->id, 'semester_number' => 5, 'type' => 'theory', 'code' => 'CSE-5501',
        'name' => 'Machine Learning via MCP', 'credit_hours' => 3, 'idempotencyKey' => 'e2e-10',
    ]);

    expect($created['isError'])->toBeFalse()->and($created['payload']['data']['code'])->toBe('CSE-5501');

    $page = uiLogin(T::SUPER_ADMIN)->assertSee('Two-factor verification');
    $page->fill('input[autocomplete=one-time-code]', totpCode('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 1))->click('Verify')->wait(2)->assertPathIs('/');
    $page->navigate('/courses')->assertSee('Machine Learning via MCP')->assertSee('CSE-5501');

    $audit = App\Models\AuditLog::query()->where('action', 'course.created')->firstOrFail();
    expect($audit->channel->value)->toBe('mcp');
});
