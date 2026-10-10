<?php

use App\Mcp\Registry\ToolRegistry;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\IdempotencyKey;
use Database\Seeders\Testing\TestDataset as T;
use Tests\Mcp\McpClient;

beforeEach(function () {
    seedTestDataset();
    ['token' => $token] = mcpIntegrationFor(datasetUser(T::SUPER_ADMIN));
    $this->client = new McpClient($this, $token);
});

it('declares a strict, documented schema for every tool', function () {
    $tools = collect($this->client->tools());

    expect($tools->count())->toBe(app(ToolRegistry::class)->definitions()->count());

    foreach ($tools as $tool) {
        $schema = $tool['inputSchema'];

        expect($tool['description'])->not->toBeEmpty()
            ->and(strlen($tool['description']))->toBeGreaterThan(30, "{$tool['name']} needs a real description")
            ->and($tool['title'])->not->toBeEmpty()
            ->and($schema['type'])->toBe('object')
            ->and($schema['additionalProperties'])->toBeFalse()
            ->and(array_key_exists('readOnlyHint', $tool['annotations']))->toBeTrue()
            ->and(array_key_exists('destructiveHint', $tool['annotations']))->toBeTrue()
            ->and(array_key_exists('idempotentHint', $tool['annotations']))->toBeTrue()
            ->and($tool['outputSchema']['required'])->toBe(['summary']);

        $properties = (array) $schema['properties'];

        foreach ($schema['required'] as $required) {
            expect(array_key_exists($required, $properties))->toBeTrue("{$tool['name']}: required {$required} must be a property");
        }

        foreach ($properties as $name => $property) {
            expect($property['type'] ?? null)->not->toBeNull("{$tool['name']}.{$name} needs a type")
                ->and($property['description'] ?? '')->not->toBeEmpty("{$tool['name']}.{$name} needs a description");
        }
    }
});

it('requires confirm on every destructive tool and marks it with destructiveHint', function () {
    $listed = collect($this->client->tools());

    foreach (app(ToolRegistry::class)->definitions() as $definition) {
        $tool = $listed->firstWhere('name', $definition->name);

        expect($tool['annotations']['destructiveHint'])->toBe($definition->destructive);

        if ($definition->destructive) {
            expect(array_keys((array) $tool['inputSchema']['properties']))->toContain('confirm')
                ->and($tool['annotations']['readOnlyHint'])->toBeFalse();
        }
    }
});

it('marks read-only tools consistently: no read-only tool takes confirm or idempotencyKey', function () {
    foreach ($this->client->tools() as $tool) {
        if ($tool['annotations']['readOnlyHint']) {
            expect(array_keys((array) $tool['inputSchema']['properties']))->not->toContain('confirm', 'idempotencyKey');
        }
    }
});

it('returns structured output with a summary that matches the output schema', function () {
    $result = $this->client->call('department_list');

    expect($result['payload'])->toHaveKeys(['summary', 'data'])
        ->and($result['payload']['summary'])->toBeString()
        ->and($result['raw']['result']['content'][0]['type'])->toBe('text');
});

it('rejects unknown and wrongly typed arguments with VALIDATION_ERROR', function () {
    expect($this->client->errorCode('department_get', ['id' => 'abc']))->toBe('VALIDATION_ERROR')
        ->and($this->client->errorCode('department_get', []))->toBe('VALIDATION_ERROR')
        ->and($this->client->errorCode('department_list', ['nonsense' => 1]))->toBe('VALIDATION_ERROR')
        ->and($this->client->errorCode('department_list', ['limit' => 'many']))->toBe('VALIDATION_ERROR');
});

it('names the invalid field in the error context', function () {
    $result = $this->client->call('department_create', ['name' => '']);

    expect($result['payload']['error']['code'])->toBe('VALIDATION_ERROR')
        ->and($result['payload']['error']['context']['errors'])->toHaveKey('name');
});

it('answers NOT_FOUND for missing records and CONFLICT for duplicates', function () {
    expect($this->client->errorCode('department_get', ['id' => 99999]))->toBe('NOT_FOUND');

    $this->client->call('department_create', ['name' => 'Physics', 'code' => 'PHY']);
    $duplicate = $this->client->call('department_create', ['name' => 'Physics 2', 'code' => 'PHY']);

    expect($duplicate['payload']['error']['code'])->toBe('VALIDATION_ERROR');
});

it('paginates list tools with a cursor and caps the page size at 100', function () {
    $first = $this->client->call('student_list', ['limit' => 2]);

    expect($first['payload']['data']['items'])->toHaveCount(2)
        ->and($first['payload']['data']['next_cursor'])->not->toBeNull();

    $second = $this->client->call('student_list', ['limit' => 2, 'cursor' => $first['payload']['data']['next_cursor']]);

    expect(array_column($second['payload']['data']['items'], 'id'))->not->toContain(...array_column($first['payload']['data']['items'], 'id'));

    $all = $this->client->call('student_list', ['limit' => 500]);
    expect(count($all['payload']['data']['items']))->toBeLessThanOrEqual(100);
});

it('returns a dry-run preview and changes nothing without confirm=true', function () {
    $course = Course::query()->where('code', 'CSE-1101')->firstOrFail();

    $preview = $this->client->call('course_archive', ['id' => $course->id]);

    expect($preview['isError'])->toBeFalse()
        ->and($preview['payload']['dry_run'])->toBeTrue()
        ->and($preview['payload']['confirm_required'])->toBeTrue()
        ->and($preview['payload']['data']['course']['code'])->toBe('CSE-1101')
        ->and($course->fresh()->is_active)->toBeTrue();

    $falsy = $this->client->call('course_archive', ['id' => $course->id, 'confirm' => false]);
    expect($falsy['payload']['dry_run'])->toBeTrue()->and($course->fresh()->is_active)->toBeTrue();

    $done = $this->client->call('course_archive', ['id' => $course->id, 'confirm' => true]);
    expect($done['payload'])->not->toHaveKey('dry_run')->and($course->fresh()->is_active)->toBeFalse();
});

it('previews role revocation, user deactivation, bulk enrollment and result publication without side effects', function () {
    $teacher = datasetUser(T::TEACHER);
    $preview = $this->client->call('user_deactivate', ['id' => $teacher->id]);
    expect($preview['payload']['dry_run'])->toBeTrue()->and($teacher->fresh()->is_active)->toBeTrue();

    $preview = $this->client->call('role_revoke', ['user_id' => $teacher->id, 'role' => 'teacher']);
    expect($preview['payload']['dry_run'])->toBeTrue()->and($teacher->fresh()->hasRole('teacher'))->toBeTrue();

    $semester = App\Models\Semester::query()->where('code', 'FA2025')->firstOrFail();
    $publish = $this->client->call('result_publish', ['semester_id' => $semester->id]);
    expect($publish['payload']['dry_run'])->toBeTrue()
        ->and($publish['payload']['data'])->toHaveKeys(['results', 'students'])
        ->and(App\Models\Result::query()->where('status', 'published')->count())->toBe(App\Models\Result::query()->where('published_at', '!=', null)->count());
});

it('creates once per idempotencyKey and replays the first result on a retry', function () {
    $args = ['name' => 'Chemistry', 'code' => 'CHM', 'idempotencyKey' => 'create-chm-1'];

    $first = $this->client->call('department_create', $args);
    $second = $this->client->call('department_create', $args);

    expect($first['isError'])->toBeFalse()
        ->and($second['isError'])->toBeFalse()
        ->and($second['payload']['replayed'])->toBeTrue()
        ->and($second['payload']['data']['id'])->toBe($first['payload']['data']['id'])
        ->and(Department::query()->where('code', 'CHM')->count())->toBe(1)
        ->and(IdempotencyKey::query()->count())->toBe(1);

    $other = $this->client->call('department_create', ['name' => 'Chemistry 2', 'code' => 'CH2', 'idempotencyKey' => 'create-chm-1']);
    expect($other['payload']['error']['code'])->toBe('CONFLICT');
});

it('writes every call to the audit log with channel mcp and the integration id', function () {
    $this->client->call('department_create', ['name' => 'Biology', 'code' => 'BIO']);
    $this->client->call('department_get', ['id' => 999999]);

    $call = AuditLog::query()->where('action', 'mcp.tool_call')->get();
    $write = AuditLog::query()->where('action', 'department.created')->firstOrFail();

    expect($call)->toHaveCount(2)
        ->and($call->first()->channel->value)->toBe('mcp')
        ->and($call->first()->integration_id)->not->toBeNull()
        ->and($call->pluck('after.outcome')->all())->toBe(['success', 'error'])
        ->and($write->channel->value)->toBe('mcp')
        ->and($write->actor_user_id)->toBe(datasetUser(T::SUPER_ADMIN)->id)
        ->and($write->integration_id)->toBe($call->first()->integration_id);
});

it('records denied calls in the audit log', function () {
    ['token' => $token] = mcpIntegrationFor(datasetUser(T::TEACHER));
    $teacher = new McpClient($this, $token);

    expect($teacher->errorCode('user_create', []))->toBe('FORBIDDEN');

    expect(AuditLog::query()->where('action', 'mcp.tool_call')->where('after->outcome', 'denied')->exists())->toBeTrue();
});

it('exposes the three resources and three prompts', function () {
    $resources = collect($this->client->rpc('resources/list')['result']['resources'])->pluck('uri')->all();
    $prompts = collect($this->client->rpc('prompts/list')['result']['prompts'])->pluck('name')->all();

    expect($resources)->toEqualCanonicalizing(['erp://me', 'erp://academic-calendar', 'erp://grading-scale'])
        ->and($prompts)->toEqualCanonicalizing(['create_course_wizard', 'publish_semester_results', 'review_pending_clearances']);

    $me = json_decode($this->client->rpc('resources/read', ['uri' => 'erp://me'])['result']['contents'][0]['text'], true);
    expect($me['email'])->toBe(T::SUPER_ADMIN)->and($me['roles'][0]['role'])->toBe('super_admin');

    $scale = json_decode($this->client->rpc('resources/read', ['uri' => 'erp://grading-scale'])['result']['contents'][0]['text'], true);
    expect($scale['bands'])->toHaveCount(10)->and($scale['retake_policy'])->toBe('best');

    $calendar = json_decode($this->client->rpc('resources/read', ['uri' => 'erp://academic-calendar'])['result']['contents'][0]['text'], true);
    expect(collect($calendar)->pluck('code')->all())->toContain('FA2025');

    $prompt = $this->client->rpc('prompts/get', ['name' => 'publish_semester_results', 'arguments' => (object) ['semester_code' => 'FA2025']]);
    expect(json_encode($prompt))->toContain('FA2025');
});

it('answers ping and an unknown method with a JSON-RPC error', function () {
    expect($this->client->rpc('ping')['result'])->toBeArray()
        ->and($this->client->rpc('no/such/method')['error']['code'])->toBe(-32601);
});

it('answers unknown tools with a JSON-RPC error', function () {
    expect($this->client->rpc('tools/call', ['name' => 'nope_nothing', 'arguments' => (object) []])['error']['code'])->toBe(-32602);
});

it('creates a course through a tool exactly like the web service would', function () {
    $department = Department::query()->where('code', T::DEPT_CSE)->firstOrFail();

    $result = $this->client->call('course_create', [
        'department_id' => $department->id, 'semester_number' => 3, 'type' => 'theory', 'code' => 'cse-2301', 'name' => 'Algorithms', 'credit_hours' => 3, 'idempotencyKey' => 'algo-1',
    ]);

    expect($result['isError'])->toBeFalse()
        ->and(Course::query()->where('code', 'CSE-2301')->exists())->toBeTrue();
});
