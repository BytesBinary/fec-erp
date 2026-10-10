<?php

use App\Exceptions\Domain\ValidationException;
use App\Exceptions\Mcp\McpAuthenticationException;
use App\Filament\Pages\Clearance\ViewClearance;
use App\Filament\Pages\Settings\AiIntegrations;
use App\Mcp\Methods\Concerns\RevalidatesIntegration;
use App\Models\Department;
use App\Models\Result;
use App\Services\Assistant\Providers\FakeProvider;
use App\Services\Mcp\IntegrationService;
use App\Services\Profile\ProfileService;
use App\Services\Results\ResultService;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Livewire\Livewire;

beforeEach(function () {
    seedTestDataset();
});

it('stops a long-lived connection (stdio) on the next request once the integration is stopped', function () {
    $user = datasetUser(T::TEACHER);
    ['integration' => $integration] = mcpIntegrationFor($user);

    $guard = new class
    {
        use RevalidatesIntegration;

        public function check(JsonRpcRequest $request): void
        {
            $this->revalidateIntegration($request);
        }
    };

    app()->instance('mcp.integration', $integration);
    $request = new JsonRpcRequest(1, 'tools/list', []);

    $guard->check($request);

    app(IntegrationService::class)->revoke($user, $integration);

    expect(fn () => $guard->check($request))->toThrow(JsonRpcException::class, 'TOKEN_REVOKED');
    expect(fn () => app(IntegrationService::class)->revalidate($integration))->toThrow(McpAuthenticationException::class);
});

it('stops a long-lived connection when the user turns 2FA off', function () {
    $user = datasetUser(T::TEACHER);
    ['integration' => $integration] = mcpIntegrationFor($user);

    App\Models\UserMfa::query()->where('user_id', $user->id)->delete();

    expect(fn () => app(IntegrationService::class)->revalidate($integration))->toThrow(McpAuthenticationException::class);
});

it('runs a confirmation card at most once, even when two requests race', function () {
    config(['assistant.provider' => 'fake']);
    FakeProvider::reset();

    $response = $this->flushSession()->actingAs(datasetUser(T::SUPER_ADMIN))->postJson(route('assistant.chat'), ['message' => 'create a department called Botany code BOT']);
    preg_match('/"type":"confirm","id":(\d+)/', $response->streamedContent(), $match);
    $id = (int) $match[1];

    $lock = Cache::lock("assistant-action:{$id}", 30);
    expect($lock->get())->toBeTrue();

    $this->flushSession()->actingAs(datasetUser(T::SUPER_ADMIN))->postJson(route('assistant.confirm', $id))->assertStatus(409);
    expect(Department::query()->where('code', 'BOT')->exists())->toBeFalse();

    $lock->release();

    $this->flushSession()->actingAs(datasetUser(T::SUPER_ADMIN))->postJson(route('assistant.confirm', $id))->assertOk();
    $this->flushSession()->actingAs(datasetUser(T::SUPER_ADMIN))->postJson(route('assistant.confirm', $id))->assertStatus(409);
    expect(Department::query()->where('code', 'BOT')->count())->toBe(1);
});

it('picks the same "latest" attempt for the CGPA as the transcript, whatever the result ids are', function () {
    config(['grading.retake_policy' => 'latest']);

    $student = studentFor(T::STUDENT_ELIGIBLE);
    $user = datasetUser(T::STUDENT_ELIGIBLE);

    $failed = Result::query()->where('letter', 'F')->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->id))->firstOrFail();
    $failed->newQuery()->whereKey($failed->id)->update(['id' => 900000]);

    $service = app(ResultService::class);

    expect($service->cgpa($user, $student)->display())->toBe($service->transcript($user, $student)['cgpa_display']);
});

it('only accepts profile photos that live in the photo directory', function (string $path) {
    $student = studentFor(T::STUDENT_INCOMPLETE);

    expect(fn () => app(ProfileService::class)->save(datasetUser(T::STUDENT_INCOMPLETE), $student, ['photo_path' => $path]))
        ->toThrow(ValidationException::class);
})->with(['../../.env', 'student-photos/../../.env', 'other-folder/photo.png', '/etc/passwd']);

it('does not let the browser rewrite the integration ids the wizard keeps on the server', function () {
    $this->actingAs(datasetUser(T::TEACHER));

    Livewire::test(AiIntegrations::class)->set('newIntegrationId', 1);
})->throws(Exception::class, 'Cannot update locked property');

it('does not let the browser point the clearance page at another request', function () {
    $this->actingAs(datasetUser(T::LIBRARIAN));
    $request = applyForClearance();

    Livewire::test(ViewClearance::class, ['record' => $request->id])->set('recordId', $request->id + 1);
})->throws(Exception::class, 'Cannot update locked property');

it('cannot stop or rename another user\'s integration from the integrations page', function () {
    ['integration' => $foreign] = mcpIntegrationFor(datasetUser(T::LIBRARIAN));
    $this->actingAs(datasetUser(T::TEACHER));
    mcpIntegrationFor(datasetUser(T::TEACHER));

    expect(fn () => Livewire::test(AiIntegrations::class)->callAction('stop', arguments: ['integration' => $foreign->id]))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect($foreign->fresh()->revoked_at)->toBeNull();
});
