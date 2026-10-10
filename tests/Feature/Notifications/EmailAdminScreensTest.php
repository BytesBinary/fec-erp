<?php

use App\Enums\EmailDeliveryStatus;
use App\Filament\Resources\EmailDeliveries\Pages\ListEmailDeliveries;
use App\Filament\Resources\EmailDeliveries\Pages\ViewEmailDelivery;
use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Filament\Resources\NotificationRules\Pages\ListNotificationRules;
use App\Models\EmailDelivery;
use App\Models\EmailTemplate;
use App\Models\NotificationRule;
use App\Services\Notifications\Contracts\ExternalMessenger;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;
use Tests\Support\RecordingMessenger;

beforeEach(function () {
    seedTestDataset();
    config(['notifications.enabled' => true]);
    $this->messenger = new RecordingMessenger;
    app()->instance(ExternalMessenger::class, $this->messenger);
});

describe('Email notifications (event rules)', function () {
    it('lists every event grouped by category for the super admin, with the page in the menu', function () {
        $this->actingAs(datasetUser(T::SUPER_ADMIN))->get('/email-notifications')->assertOk()
            ->assertSee('Email notifications')->assertSee('Email templates')->assertSee('Email deliveries')
            ->assertSee('Account &amp; security', false)->assertSee('Result portal')->assertSee('Password changed');

        expect(NotificationRule::query()->count())->toBe(count(config('notification_events.events')));
    });

    it('configures, toggles, previews, tests and resets a rule', function () {
        $admin = datasetUser(T::SUPER_ADMIN);
        $rule = app(App\Services\Notifications\NotificationRules::class)->forEvent('library.loan_overdue');
        $component = Livewire::actingAs($admin)->test(ListNotificationRules::class);

        $component->callTableAction('editRule', $rule, ['enabled' => true, 'mode' => 'digest', 'recipients' => ['affected_user', 'role:librarian'], 'email_template_id' => null])->assertNotified();

        expect($rule->fresh()->mode)->toBe('digest')->and($rule->fresh()->recipients)->toBe(['affected_user', 'role:librarian']);

        $component->callTableAction('toggle', $rule->fresh());
        expect($rule->fresh()->enabled)->toBeFalse();

        $component->mountTableAction('preview', $rule)->assertHasNoErrors();

        $component->callTableAction('sendTest', $rule);
        expect(EmailDelivery::query()->where('is_test', true)->count())->toBe(1)->and($this->messenger->sent[0]['email'])->toBe($admin->email);

        $component->callTableAction('reset', $rule->fresh());
        expect($rule->fresh()->enabled)->toBeTrue()->and($rule->fresh()->mode)->toBe('immediate');
    });

    it('shows the admin office the rules but no way to change them', function () {
        $office = datasetUser(T::ADMIN_OFFICE);
        $rule = app(App\Services\Notifications\NotificationRules::class)->forEvent('library.loan_overdue');

        Livewire::actingAs($office)->test(ListNotificationRules::class)
            ->assertCanSeeTableRecords([$rule])
            ->assertTableActionHidden('editRule', $rule)
            ->assertTableActionHidden('toggle', $rule)
            ->assertTableActionHidden('sendTest', $rule)
            ->assertTableActionVisible('preview', $rule);
    });

    it('keeps everyone else out of all three email screens', function () {
        foreach (['/email-notifications', '/email-templates', '/email-deliveries'] as $path) {
            $this->flushSession();
            $this->actingAs(datasetUser(T::TEACHER))->get($path)->assertForbidden();
            $this->flushSession();
            $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE))->get($path)->assertForbidden();
            $this->flushSession();
            $this->actingAs(datasetUser(T::DEPT_HEAD_CSE))->get($path)->assertForbidden();
        }
    });
});

describe('Email templates', function () {
    it('creates, edits and deletes a template and rejects unknown placeholders and secrets', function () {
        $admin = datasetUser(T::SUPER_ADMIN);

        Livewire::actingAs($admin)->test(CreateEmailTemplate::class)
            ->fillForm(['event_key' => 'security.password_changed', 'name' => 'Short', 'subject' => 'Password changed', 'body' => 'Hi {recipient_name}, {signed_out_devices} device(s) signed out.'])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = EmailTemplate::query()->sole();

        Livewire::actingAs($admin)->test(CreateEmailTemplate::class)
            ->fillForm(['event_key' => 'security.password_changed', 'name' => 'Bad', 'subject' => 'x', 'body' => 'Your password is {password}'])
            ->call('create')
            ->assertNotified();

        expect(EmailTemplate::query()->count())->toBe(1);

        Livewire::actingAs($admin)->test(EditEmailTemplate::class, ['record' => $template->getKey()])
            ->fillForm(['name' => 'Shorter', 'subject' => 'Changed', 'body' => 'Hello {recipient_name}'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($template->fresh()->name)->toBe('Shorter');

        Livewire::actingAs($admin)->test(ListEmailTemplates::class)->assertCanSeeTableRecords([$template])->callTableAction('delete', $template);

        expect(EmailTemplate::query()->count())->toBe(0);
    });

    it('does not let the admin office edit templates', function () {
        $template = EmailTemplate::factory()->create();

        Livewire::actingAs(datasetUser(T::ADMIN_OFFICE))->test(ListEmailTemplates::class)->assertCanSeeTableRecords([$template])->assertTableActionHidden('delete', $template);
    });
});

describe('Email deliveries', function () {
    it('shows successes and failures in tabs and retries failed ones', function () {
        $office = datasetUser(T::ADMIN_OFFICE);
        $sent = EmailDelivery::factory()->create(['status' => EmailDeliveryStatus::Sent]);
        $failed = EmailDelivery::factory()->create(['status' => EmailDeliveryStatus::Failed, 'last_error' => 'SMTP connection refused', 'recipient_email' => 'x@example.com']);

        $component = Livewire::actingAs($office)->test(ListEmailDeliveries::class)
            ->assertCanSeeTableRecords([$sent, $failed])
            ->set('activeTab', 'failed')
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$sent])
            ->assertSee('SMTP connection refused');

        $component->callTableAction('retry', $failed);

        expect($failed->fresh()->status)->toBe(EmailDeliveryStatus::Sent)->and($this->messenger->sent[0]['email'])->toBe('x@example.com');

        $again = EmailDelivery::factory()->create(['status' => EmailDeliveryStatus::Failed]);
        Livewire::actingAs($office)->test(ListEmailDeliveries::class)->callAction('retryAllFailed')->assertNotified();

        expect($again->fresh()->status)->toBe(EmailDeliveryStatus::Sent);
    });

    it('shows one email in full', function () {
        $delivery = EmailDelivery::factory()->create(['subject' => 'Hello there', 'body' => "Line one\nLine two"]);

        Livewire::actingAs(datasetUser(T::SUPER_ADMIN))->test(ViewEmailDelivery::class, ['record' => $delivery->getKey()])->assertSee('Hello there')->assertSee('Line one');
    });
});
