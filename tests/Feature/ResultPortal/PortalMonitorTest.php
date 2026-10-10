<?php

use App\Exceptions\Domain\ForbiddenException;
use App\Filament\Pages\Portal\PortalMonitor;
use App\Models\Batch;
use App\Models\PortalCheckRun;
use App\Models\PortalExam;
use App\Models\PortalPublication;
use App\Services\ResultPortal\PortalMonitor as PortalMonitorService;
use Carbon\Carbon;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Mcp\McpClient;

beforeEach(function () {
    seedTestDataset();
    Carbon::setTestNow('2026-10-10 09:00:00');
    Cache::forget('result_portal.sessions');
    Batch::query()->update(['session' => '2022-2023']);

    config([
        'result_portal.enabled' => true,
        'result_portal.shadow_mode' => true,
        'result_portal.request_delay_ms' => 0,
        'result_portal.department_programs' => [T::DEPT_CSE => 14, T::DEPT_EEE => 13, 'CE' => 12],
    ]);

    $this->extra = '';
    $this->down = false;

    Http::fake(function (Request $request) {
        if ($this->down) {
            return Http::response('down', 503);
        }

        if (str_contains($request->url(), 'result.php')) {
            return Http::response('<option value="23">2022-2023</option>');
        }

        if ($request->method() === 'POST') {
            return Http::response(portalFixture('not_verified'));
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response(match ((int) $query['program_id']) {
            14 => '<option value="1221">B.Sc. in Computer Science and Engineering 1st year 2nd Semester Examination of 2023</option>'.$this->extra,
            13 => '<option value="2221">B.Sc. in Electrical and Electronic Engineering 1st year 2nd Semester Examination of 2023</option>',
            default => '<option value="3221">B.Sc. in Civil Engineering 1st year 2nd Semester Examination of 2023</option>',
        });
    });
});

describe('the monitor service', function () {
    it('lets the admin office see and run it, and nobody below', function () {
        $service = app(PortalMonitorService::class);

        expect($service->status(datasetUser(T::ADMIN_OFFICE)))->toHaveKeys(['enabled', 'shadow_mode', 'next_check_at', 'exams_by_program', 'publications', 'pull_counts'])
            ->and($service->status(datasetUser(T::ADMIN_OFFICE))['next_check_at']->toDateTimeString())->toBe('2026-10-11 06:00:00')
            ->and($service->syncCatalog(datasetUser(T::ADMIN_OFFICE)))->toBe(['total' => 3, 'new' => 0]);

        foreach ([T::DEPT_HEAD_CSE, T::TEACHER, T::STUDENT_ELIGIBLE] as $email) {
            expect(fn () => $service->status(datasetUser($email)))->toThrow(ForbiddenException::class, 'portal_monitor:view');
        }

        expect(fn () => $service->checkNow(datasetUser(T::LIBRARIAN)))->toThrow(ForbiddenException::class);
    });

    it('groups the saved exams by department and year', function () {
        app(PortalMonitorService::class)->syncCatalog(datasetUser(T::SUPER_ADMIN));

        $status = app(PortalMonitorService::class)->status(datasetUser(T::SUPER_ADMIN));

        expect($status['exams_by_program'][14])->toMatchArray(['label' => 'CSE', 'total' => 1])
            ->and($status['exams_by_program'][14]['by_year'])->toBe(['2023' => 1])
            ->and($status['last_success']->kind)->toBe('catalog');
    });

    it('reports a failed sync as a validation error and keeps the failure for the screen', function () {
        $this->down = true;

        expect(fn () => app(PortalMonitorService::class)->syncCatalog(datasetUser(T::SUPER_ADMIN)))->toThrow(App\Exceptions\Domain\ValidationException::class, 'could not be reached');

        expect(PortalCheckRun::query()->sole()->status)->toBe('failed');
    });

    it('refuses to run a publication that is not confirmed', function () {
        $publication = PortalPublication::factory()->create(['status' => 'awaiting']);

        expect(fn () => app(PortalMonitorService::class)->runPublication(datasetUser(T::SUPER_ADMIN), $publication))->toThrow(App\Exceptions\Domain\ValidationException::class, 'awaiting');
    });
});

describe('the Portal monitor screen', function () {
    it('shows shadow mode, saves the exam list with the first button and finds a new exam with the second', function () {
        $admin = datasetUser(T::SUPER_ADMIN);

        Livewire::actingAs($admin)->test(PortalMonitor::class)
            ->assertSee('Shadow mode is on')
            ->assertSee('Never')
            ->callAction('syncCatalog')
            ->assertNotified()
            ->assertSee('CSE')
            ->assertSee('2023: 1');

        expect(PortalExam::query()->count())->toBe(3);

        $this->extra = '<option value="1563">B.Sc. in Computer Science and Engineering 2nd year 1st Semester Examination of 2024</option>';

        Livewire::actingAs($admin)->test(PortalMonitor::class)
            ->callAction('checkNow')
            ->assertNotified()
            ->assertSeeHtml('data-testid="publication-row"')
            ->assertSee('Awaiting');

        expect(PortalPublication::query()->sole()->portal_exam_id)->toBe(1563);
    });

    it('is open to the admin office and closed to department heads and students', function () {
        $this->actingAs(datasetUser(T::ADMIN_OFFICE))->get('/portal-monitor')->assertOk()->assertSee('Result portal monitor');
        $this->flushSession();
        $this->actingAs(datasetUser(T::DEPT_HEAD_CSE))->get('/portal-monitor')->assertForbidden();
        $this->flushSession();
        $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE))->get('/portal-monitor')->assertForbidden();
    });
});

describe('commands and MCP', function () {
    it('syncs and checks from the command line', function () {
        expect(Artisan::call('portal:sync-catalog'))->toBe(0)
            ->and(PortalExam::query()->count())->toBe(3)
            ->and(Artisan::call('portal:check'))->toBe(0);

        config(['result_portal.enabled' => false]);

        expect(Artisan::call('portal:check'))->toBe(0)->and(Artisan::output())->toContain('switched off');
    });

    it('lets an admin drive the monitor through MCP and keeps it from others', function () {
        ['token' => $token] = mcpIntegrationFor(datasetUser(T::ADMIN_OFFICE));
        $client = new McpClient($this, $token);

        $sync = $client->call('portal_catalog_sync');
        $status = $client->call('portal_monitor_get');

        expect($sync['isError'])->toBeFalse()->and($sync['payload']['data'])->toBe(['total' => 3, 'new' => 0])
            ->and($status['payload']['data']['shadow_mode'])->toBeTrue()
            ->and($status['payload']['data']['exams_by_program'][14]['total'])->toBe(1);

        ['token' => $teacherToken] = mcpIntegrationFor(datasetUser(T::TEACHER));

        expect((new McpClient($this, $teacherToken))->errorCode('portal_monitor_get'))->toBe('FORBIDDEN');
    });
});
