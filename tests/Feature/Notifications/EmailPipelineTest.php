<?php

use App\Enums\EmailDeliveryStatus;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ValidationException;
use App\Jobs\SendEmailDelivery;
use App\Models\AuditLog;
use App\Models\EmailDelivery;
use App\Models\EmailTemplate;
use App\Models\NotificationRule;
use App\Models\OutboxEvent;
use App\Models\User;
use App\Notifications\ClearanceNotification;
use App\Notifications\PasswordChanged;
use App\Services\Notifications\Contracts\ExternalMessenger;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\EmailTemplateService;
use App\Services\Notifications\NotificationEventRegistry;
use App\Services\Notifications\NotificationEvents;
use App\Services\Notifications\NotificationRuleService;
use App\Services\Notifications\OutboxProcessor;
use App\Services\Notifications\SecretGuard;
use App\Services\Notifications\TemplateRenderer;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\Support\RecordingMessenger;

beforeEach(function () {
    seedTestDataset();
    config(['notifications.enabled' => true]);
    $this->messenger = new RecordingMessenger;
    app()->instance(ExternalMessenger::class, $this->messenger);
});

function emit(string $key, array $context = [], ?string $dedupe = null, ?User $user = null, ?int $department = null): ?OutboxEvent
{
    return app(NotificationEvents::class)->emit($key, $context, $dedupe, $user, $department);
}

describe('events become emails', function () {
    it('renders the rule\'s template for the affected user and sends it through the queue', function () {
        $student = datasetUser(T::STUDENT_ELIGIBLE);

        emit('security.password_changed', ['signed_out_devices' => 2, 'ignored' => 'not a placeholder'], 'pw:1', $student);

        $delivery = EmailDelivery::query()->sole();

        expect($this->messenger->sent)->toHaveCount(1)
            ->and($this->messenger->sent[0]['email'])->toBe($student->email)
            ->and($this->messenger->sent[0]['subject'])->toContain(config('app.name'))
            ->and($this->messenger->sent[0]['body'])->toContain("Hello {$student->name}")->toContain('2 other device(s)')
            ->and($delivery->status)->toBe(EmailDeliveryStatus::Sent)
            ->and(OutboxEvent::query()->sole()->processed_at)->not->toBeNull()
            ->and(OutboxEvent::query()->sole()->context)->not->toHaveKey('ignored');
    });

    it('never sends the same event twice, even when it is emitted and processed again', function () {
        $student = datasetUser(T::STUDENT_ELIGIBLE);

        emit('security.password_changed', ['signed_out_devices' => 1], 'pw:same', $student);
        emit('security.password_changed', ['signed_out_devices' => 1], 'pw:same', $student);
        app(OutboxProcessor::class)->process();

        expect(OutboxEvent::query()->count())->toBe(1)->and(EmailDelivery::query()->count())->toBe(1)->and($this->messenger->sent)->toHaveCount(1);
    });

    it('records nothing at all for an event an admin switched off', function () {
        NotificationRule::query()->where('event_key', 'security.password_changed')->delete();
        app(NotificationRuleService::class)->update(datasetUser(T::SUPER_ADMIN), 'security.password_changed', ['enabled' => false]);

        emit('security.password_changed', ['signed_out_devices' => 1], 'pw:off', datasetUser(T::STUDENT_ELIGIBLE));

        expect(EmailDelivery::query()->count())->toBe(0)->and(OutboxEvent::query()->count())->toBe(0);
    });

    it('knows only the events in the registry', function () {
        expect(fn () => emit('no.such_event'))->toThrow(InvalidArgumentException::class);
    });

    it('does nothing when the whole email system is switched off', function () {
        config(['notifications.enabled' => false]);

        expect(emit('security.password_changed', [], 'x', datasetUser(T::STUDENT_ELIGIBLE)))->toBeNull()->and(OutboxEvent::query()->count())->toBe(0);
    });

    it('picks up events a crash left in the outbox', function () {
        OutboxEvent::factory()->create(['event_key' => 'security.password_changed', 'dedupe_key' => 'crash:1', 'context' => ['signed_out_devices' => '3'], 'affected_user_id' => datasetUser(T::STUDENT_ELIGIBLE)->id]);

        expect(EmailDelivery::query()->count())->toBe(0);

        Artisan::call('notifications:process-outbox');

        expect(EmailDelivery::query()->sole()->status)->toBe(EmailDeliveryStatus::Sent);
    });

    it('turns existing bell notifications into emails through the same pipeline', function () {
        $student = datasetUser(T::STUDENT_ELIGIBLE);

        $student->notify(new PasswordChanged(4));
        $student->notify(new ClearanceNotification('Stage approved', 'The library stage approved request CLR-1.', 'https://erp.test/clearance', 'success', 'clearance.approved'));

        $keys = EmailDelivery::query()->pluck('event_key')->all();
        $approved = EmailDelivery::query()->where('event_key', 'clearance.approved')->sole();

        expect($keys)->toEqualCanonicalizing(['security.password_changed', 'clearance.approved'])
            ->and($approved->body)->toContain('The library stage approved request CLR-1.')->toContain('https://erp.test/clearance')
            ->and($student->notifications()->count())->toBe(2);
    });
});

describe('who receives it', function () {
    it('gives a department head only the events of their own department', function () {
        $cse = studentFor(T::STUDENT_ELIGIBLE);

        emit('student.added_admin_copy', ['student' => 'Arif', 'roll' => '1', 'department' => 'CSE', 'batch' => 'B1'], 'copy:1', null, $cse->department_id);
        NotificationRule::query()->where('event_key', 'student.added_admin_copy')->update(['enabled' => true, 'mode' => 'immediate']);
        emit('student.added_admin_copy', ['student' => 'Arif', 'roll' => '1', 'department' => 'CSE', 'batch' => 'B1'], 'copy:2', null, $cse->department_id);

        $emails = EmailDelivery::query()->pluck('recipient_email')->all();

        expect($emails)->toBe([datasetUser(T::DEPT_HEAD_CSE)->email])->and($emails)->not->toContain(datasetUser(T::DEPT_HEAD_EEE)->email);
    });

    it('reaches every holder of a role for role recipients', function () {
        emit('system.portal_health_failed', ['reason' => 'down'], 'health:1');

        expect(EmailDelivery::query()->pluck('recipient_email')->all())->toBe([datasetUser(T::SUPER_ADMIN)->email]);
    });

    it('records a skipped delivery with the reason when the person has no usable address', function () {
        $student = datasetUser(T::STUDENT_ELIGIBLE);
        User::query()->whereKey($student->id)->update(['email' => 'not-an-address']);
        $student->refresh();

        emit('security.password_changed', ['signed_out_devices' => 1], 'pw:skip', $student);

        $delivery = EmailDelivery::query()->where('event_key', 'security.password_changed')->sole();

        expect($delivery->status)->toBe(EmailDeliveryStatus::Skipped)->and($delivery->last_error)->toContain('no valid email address')->and($this->messenger->sent)->toBe([]);
    });

    it('can mail extra addresses named by the event, such as the old address after an email change', function () {
        app(NotificationEvents::class)->emit('security.email_changed', ['new_email' => 'j***@example.com'], 'mail:1', datasetUser(T::STUDENT_ELIGIBLE), emails: ['old.address@example.com']);

        expect(EmailDelivery::query()->pluck('recipient_email')->all())->toEqualCanonicalizing([datasetUser(T::STUDENT_ELIGIBLE)->email, 'old.address@example.com']);
    });
});

describe('digests', function () {
    it('holds digest events and sends one combined email per recipient', function () {
        $head = datasetUser(T::DEPT_HEAD_CSE);
        $department = studentFor(T::STUDENT_ELIGIBLE)->department_id;

        emit('result.submitted', ['course' => 'CSE-3101', 'count' => 10], 'sub:1', null, $department);
        emit('result.submitted', ['course' => 'CSE-3102', 'count' => 12], 'sub:2', null, $department);

        expect($this->messenger->sent)->toBe([])->and(EmailDelivery::query()->where('status', EmailDeliveryStatus::Held->value)->count())->toBe(2);

        $created = app(EmailDeliveryService::class)->sendDigests();

        expect($created)->toBe(1)
            ->and($this->messenger->sent)->toHaveCount(1)
            ->and($this->messenger->sent[0]['email'])->toBe($head->email)
            ->and($this->messenger->sent[0]['body'])->toContain('2 new notification(s)')->toContain('waiting for your approval')
            ->and(EmailDelivery::query()->where('status', EmailDeliveryStatus::Digested->value)->count())->toBe(2)
            ->and(app(EmailDeliveryService::class)->sendDigests())->toBe(0);
    });
});

describe('failures and retries', function () {
    it('keeps a failing delivery queued for the next try, fails it for good after the last one, and an admin can retry it', function () {
        $delivery = EmailDelivery::factory()->create(['recipient_email' => 'someone@example.com', 'status' => EmailDeliveryStatus::Queued]);
        $this->messenger->failNext = 2;

        $job = new SendEmailDelivery($delivery->id);

        expect(fn () => $job->handle($this->messenger))->toThrow(RuntimeException::class);
        expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Queued)->and($delivery->fresh()->attempts)->toBe(1)->and($delivery->fresh()->last_error)->toContain('SMTP');

        expect(fn () => $job->handle($this->messenger))->toThrow(RuntimeException::class);
        $job->failed(new RuntimeException('SMTP connection refused'));

        expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Failed);

        app(EmailDeliveryService::class)->retry(datasetUser(T::SUPER_ADMIN), $delivery->fresh());

        expect($delivery->fresh()->status)->toBe(EmailDeliveryStatus::Sent)->and($this->messenger->sent)->toHaveCount(1);

        $job->handle($this->messenger);
        $job->handle($this->messenger);

        expect($this->messenger->sent)->toHaveCount(1);
    });

    it('refuses to retry an email that is not failed', function () {
        $delivery = EmailDelivery::factory()->create(['status' => EmailDeliveryStatus::Sent]);

        expect(fn () => app(EmailDeliveryService::class)->retry(datasetUser(T::SUPER_ADMIN), $delivery))->toThrow(ValidationException::class, 'sent');
    });

    it('raises one "emails are failing" event after repeated failures, not one per failure', function () {
        Cache::flush();

        foreach (range(1, 4) as $i) {
            $delivery = EmailDelivery::factory()->create(['status' => EmailDeliveryStatus::Queued]);
            (new SendEmailDelivery($delivery->id))->failed(new RuntimeException('boom'));
        }

        expect(OutboxEvent::query()->where('event_key', 'system.email_delivery_failing')->count())->toBe(1);
    });
});

describe('privacy and safety', function () {
    it('blocks an email whose text looks like a token instead of sending it', function () {
        emit('security.new_device_login', ['device' => 'Bearer abcdefghijklmnopqrstuvwxyz0123456789', 'ip' => '1.2.3.4', 'time' => 'now'], 'leak:1', datasetUser(T::STUDENT_ELIGIBLE));

        $delivery = EmailDelivery::query()->sole();

        expect($delivery->status)->toBe(EmailDeliveryStatus::Blocked)->and($this->messenger->sent)->toBe([]);
    });

    it('only fills the placeholders an event declares', function () {
        $template = new EmailTemplate(['subject' => 'Hi {recipient_name}', 'body' => '{device} {ip} {password} {secret}']);

        $rendered = app(TemplateRenderer::class)->render('security.new_device_login', $template, ['device' => 'Chrome', 'ip' => '1.1.1.1', 'password' => 'hunter2hunter2', 'secret' => 'abc'], 'Arif');

        expect($rendered['body'])->toBe('Chrome 1.1.1.1');
    });

    it('rejects templates with unknown placeholders or credential-like text', function () {
        $admin = datasetUser(T::SUPER_ADMIN);
        $service = app(EmailTemplateService::class);

        expect(fn () => $service->create($admin, ['event_key' => 'security.password_changed', 'name' => 'x', 'subject' => 'Hi', 'body' => 'Your password is {password}']))->toThrow(ValidationException::class, 'Unknown placeholder')
            ->and(fn () => $service->create($admin, ['event_key' => 'security.password_changed', 'name' => 'x', 'subject' => 'Hi', 'body' => 'password: hunter2hunter2']))->toThrow(ValidationException::class, 'credential');
    });

    it('keeps every registry event consistent: valid recipients, declared placeholders only, a sample for each, no secrets', function () {
        $registry = app(NotificationEventRegistry::class);
        $renderer = app(TemplateRenderer::class);
        $guard = app(SecretGuard::class);
        $roles = Spatie\Permission\Models\Role::query()->pluck('name')->all();

        foreach ($registry->all() as $key => $event) {
            expect(array_key_exists($event['category'], $registry->categories()))->toBeTrue("{$key}: category");

            foreach ($event['recipients'] as $kind) {
                $ok = in_array($kind, NotificationRuleService::KINDS, true) || (str_starts_with($kind, 'role:') && in_array(substr($kind, 5), $roles, true));
                expect($ok)->toBeTrue("{$key}: recipient {$kind}");
            }

            preg_match_all('/\{([a-z_]+)\}/', $event['subject']."\n".$event['body'], $matches);
            $undeclared = array_diff(array_unique($matches[1]), $registry->placeholders($key));
            expect($undeclared)->toBe([], "{$key} uses undeclared placeholders");
            expect(array_keys($event['sample']))->toEqualCanonicalizing($event['placeholders'], "{$key} sample");

            $rendered = $renderer->render($key, null, $renderer->sample($key), 'Test Person', 'https://erp.test');
            expect($guard->problemIn($rendered['subject']."\n".$rendered['body']))->toBeNull("{$key} default text");
            expect($rendered['body'])->not->toContain('{');
        }
    });
});

describe('administration and permissions', function () {
    it('lets only the super admin change rules; the admin office may look', function () {
        $rules = app(NotificationRuleService::class);

        expect($rules->list(datasetUser(T::ADMIN_OFFICE)))->toHaveKey('security')
            ->and(fn () => $rules->update(datasetUser(T::ADMIN_OFFICE), 'security.password_changed', ['enabled' => false]))->toThrow(ForbiddenException::class)
            ->and(fn () => $rules->list(datasetUser(T::TEACHER)))->toThrow(ForbiddenException::class)
            ->and(fn () => $rules->update(datasetUser(T::STUDENT_ELIGIBLE), 'security.password_changed', ['enabled' => false]))->toThrow(ForbiddenException::class);
    });

    it('validates changes, keeps an audit trail and can reset to the defaults', function () {
        $admin = datasetUser(T::SUPER_ADMIN);
        $rules = app(NotificationRuleService::class);

        $rule = $rules->update($admin, 'student.added', ['enabled' => false, 'mode' => 'digest', 'recipients' => ['affected_user', 'role:admin_office']]);

        expect($rule->enabled)->toBeFalse()->and($rule->mode)->toBe('digest')->and($rule->recipients)->toBe(['affected_user', 'role:admin_office'])
            ->and(AuditLog::query()->where('action', 'notification.rule_changed')->count())->toBe(1)
            ->and(fn () => $rules->update($admin, 'student.added', ['recipients' => ['everyone']]))->toThrow(ValidationException::class, 'Unknown recipient')
            ->and(fn () => $rules->update($admin, 'student.added', ['recipients' => ['role:no_such_role']]))->toThrow(ValidationException::class)
            ->and(fn () => $rules->update($admin, 'student.added', ['mode' => 'weekly']))->toThrow(ValidationException::class);

        $other = EmailTemplate::factory()->create(['event_key' => 'security.password_changed']);

        expect(fn () => $rules->update($admin, 'student.added', ['email_template_id' => $other->id]))->toThrow(ValidationException::class, 'different event');

        $reset = $rules->reset($admin, 'student.added');

        expect($reset->enabled)->toBeTrue()->and($reset->mode)->toBe('immediate')->and($reset->recipients)->toBe(['affected_user']);
    });

    it('uses a selected template instead of the default text', function () {
        $admin = datasetUser(T::SUPER_ADMIN);
        $template = app(EmailTemplateService::class)->create($admin, ['event_key' => 'security.password_changed', 'name' => 'Short', 'subject' => 'Password changed ({signed_out_devices})', 'body' => 'Hi {recipient_name}: {signed_out_devices} device(s) signed out.']);
        app(NotificationRuleService::class)->update($admin, 'security.password_changed', ['email_template_id' => $template->id]);

        emit('security.password_changed', ['signed_out_devices' => 5], 'tpl:1', datasetUser(T::STUDENT_ELIGIBLE));

        expect($this->messenger->sent[0]['subject'])->toBe('Password changed (5)')->and($this->messenger->sent[0]['body'])->toContain('5 device(s) signed out.');
    });

    it('previews a template and sends a test email to the admin only', function () {
        $admin = datasetUser(T::SUPER_ADMIN);
        $deliveries = app(EmailDeliveryService::class);

        $preview = $deliveries->preview($admin, 'library.loan_overdue', draft: ['subject' => 'Overdue: {title}', 'body' => 'Due {due}']);

        expect($preview)->toMatchArray(['subject' => 'Overdue: Introduction to Algorithms', 'body' => 'Due 10 Oct 2026', 'warning' => null]);

        $test = $deliveries->sendTest($admin, 'library.loan_overdue');

        expect($test->is_test)->toBeTrue()->and($test->recipient_email)->toBe($admin->email)->and($test->subject)->toStartWith('[TEST]')
            ->and($this->messenger->sent)->toHaveCount(1)
            ->and(fn () => $deliveries->sendTest(datasetUser(T::ADMIN_OFFICE), 'library.loan_overdue'))->toThrow(ForbiddenException::class);
    });

    it('lets the admin office see and retry deliveries but not edit rules', function () {
        EmailDelivery::factory()->create(['status' => EmailDeliveryStatus::Failed]);
        $service = app(EmailDeliveryService::class);

        expect($service->counts(datasetUser(T::ADMIN_OFFICE))['failed'])->toBe(1)
            ->and($service->retryFailed(datasetUser(T::ADMIN_OFFICE)))->toBe(1)
            ->and(fn () => $service->counts(datasetUser(T::TEACHER)))->toThrow(ForbiddenException::class);
    });
});
