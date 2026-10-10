<?php

use App\Mcp\Registry\ToolRegistry;
use Database\Seeders\Testing\TestDataset as T;
use Tests\Mcp\McpClient;

/**
 * Role × tool matrix (spec §10.4). The expected tool names per role live in
 * tests/Mcp/fixtures/role_tool_matrix.php and are reviewed by hand; regenerate
 * with `UPDATE_MCP_FIXTURE=1 php artisan test tests/Mcp/RoleToolMatrixTest.php`
 * and read the git diff before committing.
 */
beforeEach(function () {
    seedTestDataset();
    $this->fixturePath = __DIR__.'/fixtures/role_tool_matrix.php';
});

function matrixAccounts(): array
{
    return [
        'super_admin' => T::SUPER_ADMIN,
        'admin_office' => T::ADMIN_OFFICE,
        'head_of_institution' => T::HEAD_OF_INSTITUTION,
        'principal' => T::PRINCIPAL,
        'department_head' => T::DEPT_HEAD_CSE,
        'hall_provost' => T::PROVOST_A,
        'librarian' => T::LIBRARIAN,
        'teacher' => T::TEACHER,
        'student' => T::STUDENT_ELIGIBLE,
    ];
}

it('has a fixture, and regenerates it on request (UPDATE_MCP_FIXTURE=1)', function () {
    if (! getenv('UPDATE_MCP_FIXTURE')) {
        expect(file_exists($this->fixturePath))->toBeTrue();

        return;
    }

    $matrix = [];

    foreach (matrixAccounts() as $role => $email) {
        ['token' => $token] = mcpIntegrationFor(datasetUser($email));
        $matrix[$role] = (new McpClient($this, $token))->toolNames();
    }

    file_put_contents($this->fixturePath, "<?php\n\n/*\n | Expected `tools/list` per role (see RoleToolMatrixTest).\n */\n\nreturn ".var_export($matrix, true).";\n");

    expect(true)->toBeTrue();
});

it('lists exactly the expected tools for every role', function (string $role, string $email) {
    $expected = require $this->fixturePath;

    ['token' => $token] = mcpIntegrationFor(datasetUser($email));
    $actual = (new McpClient($this, $token))->toolNames();

    expect($actual)->toBe($expected[$role]);
})->with(fn () => collect(matrixAccounts())->map(fn (string $email, string $role): array => [$role, $email])->values()->all());

it('rejects every tool a role does not have with FORBIDDEN, even when the name is guessed', function (string $role, string $email) {
    $expected = require $this->fixturePath;
    $all = app(ToolRegistry::class)->definitions()->keys()->all();

    ['token' => $token] = mcpIntegrationFor(datasetUser($email));
    $client = new McpClient($this, $token);

    $missing = array_values(array_diff($all, $expected[$role]));

    if ($role === 'super_admin') {
        expect($missing)->toBe([]);
    } else {
        expect($missing)->not->toBeEmpty();
    }

    foreach ($missing as $tool) {
        $result = $client->call($tool, []);

        expect($result['isError'])->toBeTrue("{$role} calling {$tool} should fail")
            ->and($result['payload']['error']['code'] ?? null)->toBe('FORBIDDEN', "{$role} calling {$tool}");
    }
})->with(fn () => collect(matrixAccounts())->map(fn (string $email, string $role): array => [$role, $email])->values()->all());

it('gives students only self-service, results, clearance and notice tools', function () {
    $expected = require __DIR__.'/fixtures/role_tool_matrix.php';

    foreach ($expected['student'] as $name) {
        expect($name)->toMatch('/^(me_|result_get_|result_portal_get$|transcript_get|grading_scale_get|clearance_(check|apply|get_my|resubmit|cancel|get$|verify)|student_list_my_courses|notice_(list|get)|course_(list|get)$|semester_(list|get)|department_(list|get)|program_(list|get)|enrollment_list|hall_list|hall_get|student_get|help_search_features)/');
    }

    expect($expected['student'])->not->toContain('user_create', 'result_publish', 'clearance_approve', 'department_create', 'permission_matrix_update', 'audit_log_search');
});

it('gives only super admin the permission matrix, audit log and user management', function () {
    $expected = require __DIR__.'/fixtures/role_tool_matrix.php';

    foreach (['permission_matrix_update', 'audit_log_search', 'user_create', 'role_assign', 'clearance_stage_config_update', 'grading_scale_replace'] as $tool) {
        foreach ($expected as $role => $tools) {
            expect(in_array($tool, $tools, true))->toBe($role === 'super_admin', "{$role} / {$tool}");
        }
    }
});

it('shows each approver the clearance tools of their stage only', function () {
    $expected = require __DIR__.'/fixtures/role_tool_matrix.php';

    foreach (['hall_provost', 'librarian', 'department_head', 'head_of_institution'] as $role) {
        expect($expected[$role])->toContain('clearance_approve', 'clearance_reject', 'clearance_list_pending_for_me');
    }

    expect($expected['admin_office'])->toContain('clearance_search', 'clearance_print', 'clearance_mark_collected')
        ->and($expected['teacher'])->not->toContain('clearance_approve')
        ->and($expected['principal'])->not->toContain('clearance_print', 'clearance_approve');
});
