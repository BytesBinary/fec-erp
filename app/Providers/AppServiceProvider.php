<?php

namespace App\Providers;

use App\Events\McpIntegrationsStopRequested;
use App\Events\Portal\ExamCatalogUpdated;
use App\Events\Portal\PortalHealthFailed;
use App\Events\Portal\PublicationConfirmed;
use App\Events\Portal\PublicationDetected;
use App\Events\Portal\StudentGradeChanged;
use App\Events\Portal\StudentResultPulled;
use App\Events\Portal\StudentResultPullFailed;
use App\Events\TwoFactorDeactivated;
use App\Listeners\RecordRoleAndPermissionChanges;
use App\Listeners\RememberLoginPreference;
use App\Models\Enrollment;
use App\Models\HallDue;
use App\Models\HallResidency;
use App\Models\LibraryLoan;
use App\Models\Notice;
use App\Models\PortalExam;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Observers\CampusEventsObserver;
use App\Observers\NoticeObserver;
use App\Observers\StudentObserver;
use App\Observers\UserPasswordObserver;
use App\Policies\RolePolicy;
use App\Services\Assistant\Contracts\AssistantProvider;
use App\Services\Assistant\Providers\ClaudeProvider;
use App\Services\Assistant\Providers\FakeProvider;
use App\Services\Audit\AuditLogger;
use App\Services\Mcp\IntegrationService;
use App\Services\Notifications\Contracts\ExternalMessenger;
use App\Services\Notifications\LogMessenger;
use App\Services\Notifications\MailMessenger;
use App\Services\Notifications\NotificationEvents;
use App\Services\ResultPortal\Contracts\ResultSource;
use App\Services\ResultPortal\DuPortalResultSource;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\PermissionCatalog;
use App\Support\RequestContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RequestContext::class);
        $this->app->scoped(AuditLogger::class);
        $this->app->singleton(PermissionCatalog::class);
        $this->app->scoped(Authorizer::class);
        $this->app->bind(ExternalMessenger::class, fn (): ExternalMessenger => config('notifications.driver') === 'mail' ? new MailMessenger : new LogMessenger);
        $this->app->bind(ResultSource::class, DuPortalResultSource::class);
        $this->app->singleton(\PragmaRX\Google2FA\Google2FA::class);
        $this->app->bind(AssistantProvider::class, fn () => config('assistant.provider') === 'fake' ? new FakeProvider : new ClaudeProvider);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Student::observe(StudentObserver::class);
        Enrollment::observe(CampusEventsObserver::class);
        HallResidency::observe(CampusEventsObserver::class);
        HallDue::observe(CampusEventsObserver::class);
        LibraryLoan::observe(CampusEventsObserver::class);
        Semester::observe(CampusEventsObserver::class);
        Notice::observe(NoticeObserver::class);
        $this->registerPortalEventListeners();
        $this->registerSystemEventHooks();

        Gate::policy(Role::class, RolePolicy::class);

        Event::subscribe(RecordRoleAndPermissionChanges::class);
        Event::listen(Login::class, RememberLoginPreference::class);
        Event::listen(TwoFactorDeactivated::class, fn (TwoFactorDeactivated $event) => app(IntegrationService::class)->stopAllFor($event->user, null, 'Two-factor authentication was turned off or reset'));
        Event::listen(McpIntegrationsStopRequested::class, fn (McpIntegrationsStopRequested $event) => app(IntegrationService::class)->stopAllFor($event->user, $event->user, 'Stopped from the Devices page'));

        User::observe(UserPasswordObserver::class);

        RateLimiter::for('assistant', fn (Request $request): Limit => Limit::perMinute((int) config('assistant.rate_limit_per_minute'))->by((string) ($request->user()?->getKey() ?? $request->ip())));
    }

    /**
     * Result-portal events become email events (the portal code only fires
     * plain Laravel events, so it knows nothing about email).
     */
    protected function registerPortalEventListeners(): void
    {
        $events = fn (): NotificationEvents => app(NotificationEvents::class);
        $title = fn (?int $examId): string => (string) (PortalExam::query()->where('portal_exam_id', $examId)->value('title') ?? 'an exam');

        Event::listen(ExamCatalogUpdated::class, fn (ExamCatalogUpdated $event) => $events()->emit('portal.exam_catalog_updated', [
            'count' => $event->exams->count(),
            'exams' => $event->exams->take(10)->map(fn (PortalExam $exam): string => '- '.$exam->title)->implode("\n"),
            'link' => url('/portal-monitor'),
        ], 'catalog:'.$event->exams->pluck('portal_exam_id')->sort()->implode(',')));

        Event::listen(PublicationDetected::class, fn (PublicationDetected $event) => $events()->emit('portal.publication_detected', ['exam' => $title($event->publication->portal_exam_id), 'link' => url('/portal-monitor')], 'pub_detected:'.$event->publication->getKey()));

        Event::listen(PublicationConfirmed::class, fn (PublicationConfirmed $event) => $events()->emit('portal.publication_confirmed', [
            'exam' => $title($event->publication->portal_exam_id),
            'mode' => $event->publication->mode,
            'students' => $event->publication->students_total,
            'link' => url('/portal-monitor'),
        ], 'pub_confirmed:'.$event->publication->getKey()));

        Event::listen(StudentResultPulled::class, function (StudentResultPulled $event) use ($events, $title): void {
            if ($event->pull->results_changed < 1) {
                return;
            }

            $student = $event->pull->student;
            $events()->emit('student.result_pulled', ['exam' => $event->pull->portal_exam_id ? $title($event->pull->portal_exam_id) : 'your latest exam', 'link' => url('/results')], 'pulled:'.$event->pull->getKey(), $student->user, $student->department_id);
        });

        Event::listen(StudentGradeChanged::class, function (StudentGradeChanged $event) use ($events): void {
            $student = $event->pull->student;
            $events()->emit('student.grade_changed', [
                'exam' => $event->changes->first()->exam_title,
                'courses' => $event->changes->map(fn ($row): string => $row->course_code.' ('.$row->change_type->value.')')->implode(', '),
                'link' => url('/results'),
            ], 'grade:'.$event->pull->getKey(), $student->user, $student->department_id);
        });

        Event::listen(StudentResultPullFailed::class, function (StudentResultPullFailed $event) use ($events): void {
            $student = $event->pull->student;
            $events()->emit('student.result_pull_failed', ['student' => $student->user?->name, 'reason' => $event->pull->message, 'link' => url('/student-results')], 'pullfail:'.$event->pull->getKey(), null, $student->department_id);
        });

        Event::listen(PortalHealthFailed::class, fn (PortalHealthFailed $event) => $events()->emit('system.portal_health_failed', ['reason' => $event->reason, 'link' => url('/portal-monitor')], 'health:'.md5($event->reason).':'.now()->format('YmdH')));
    }

    /**
     * Failed queue jobs are reported to the super admins (job names only: the
     * payload can contain personal data). The email jobs themselves are left
     * out so a mail outage cannot report itself in a loop.
     */
    protected function registerSystemEventHooks(): void
    {
        Queue::failing(function (JobFailed $event): void {
            $name = (string) ($event->job->resolveName() ?? 'job');

            if (str_contains($name, 'SendEmailDelivery') || str_contains($name, 'ProcessOutbox')) {
                return;
            }

            app(NotificationEvents::class)->emit('system.queue_job_failed', [
                'count' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count() + 1,
                'job' => class_basename($name),
            ], 'queue_failed:'.class_basename($name).':'.now()->format('YmdH'));
        });
    }
}
