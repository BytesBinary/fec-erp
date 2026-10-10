<?php

namespace App\Filament\Pages\Results;

use App\Exceptions\Domain\DomainException;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\User;
use App\Services\Academic\CourseOfferingService;
use App\Services\Results\ResultService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Staff result workflow: teachers enter marks and submit, department heads
 * approve, authorized publishers release a semester (spec §4.2, §7).
 */
class ResultEntry extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Academic';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'result-entry';

    protected static ?string $navigationLabel = 'Result entry';

    protected string $view = 'filament.pages.results.result-entry';

    public ?int $offeringId = null;

    /**
     * Marks typed by enrollment id.
     *
     * @var array<int, string|null>
     */
    public array $marks = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        $authorizer = app(Authorizer::class);

        return $authorizer->allows($user, 'result:enter_marks') || $authorizer->allows($user, 'result:approve') || $authorizer->allows($user, 'result:publish');
    }

    /**
     * @return Collection<int, CourseOffering>
     */
    public function offerings(): Collection
    {
        return app(CourseOfferingService::class)->query($this->user())->orderByDesc('semester_id')->get();
    }

    /**
     * @return Collection<int, Enrollment>
     */
    public function roster(): Collection
    {
        if ($this->offeringId === null) {
            return collect();
        }

        return app(ResultService::class)->rosterOf($this->user(), $this->offering());
    }

    public function updatedOfferingId(): void
    {
        $this->marks = $this->roster()->mapWithKeys(fn (Enrollment $enrollment): array => [$enrollment->id => $enrollment->result?->marks !== null ? (string) $enrollment->result->marks : null])->all();
    }

    public function saveMarks(): void
    {
        $this->run(function (): string {
            $service = app(ResultService::class);
            $count = 0;

            foreach ($this->roster() as $enrollment) {
                $value = $this->marks[$enrollment->id] ?? null;

                if ($value === null || trim((string) $value) === '' || ! is_numeric($value)) {
                    continue;
                }

                $service->enterMarks($this->user(), $enrollment, (float) $value);
                $count++;
            }

            return "Saved marks for {$count} student(s).";
        });
    }

    public function submitAction(): Action
    {
        return Action::make('submit')->label('Submit to department head')->requiresConfirmation()
            ->visible(fn (): bool => $this->offeringId !== null && $this->can('result:submit'))
            ->action(fn () => $this->run(fn (): string => 'Submitted '.app(ResultService::class)->submitOffering($this->user(), $this->offering()).' result(s).'));
    }

    public function approveAction(): Action
    {
        return Action::make('approve')->label('Approve results')->color('info')->requiresConfirmation()
            ->visible(fn (): bool => $this->offeringId !== null && $this->can('result:approve'))
            ->action(fn () => $this->run(fn (): string => 'Approved '.app(ResultService::class)->approveOffering($this->user(), $this->offering()).' result(s).'));
    }

    public function publishAction(): Action
    {
        return Action::make('publish')->label('Publish semester')->color('success')->requiresConfirmation()
            ->modalDescription(function (): string {
                $semester = $this->semester();

                if ($semester === null) {
                    return 'Choose an offering first.';
                }

                $preview = app(ResultService::class)->previewPublish($this->user(), $semester);

                return "Publishing {$preview['semester']} makes {$preview['results']} approved result(s) of {$preview['students']} student(s) visible to students.";
            })
            ->visible(fn (): bool => $this->offeringId !== null && $this->can('result:publish'))
            ->action(fn () => $this->run(fn (): string => 'Published '.app(ResultService::class)->publishSemester($this->user(), $this->semester()).' result(s).'));
    }

    protected function offering(): CourseOffering
    {
        return CourseOffering::query()->with('course')->findOrFail($this->offeringId);
    }

    protected function semester(): ?Semester
    {
        return $this->offeringId === null ? null : $this->offering()->semester;
    }

    protected function can(string $permission): bool
    {
        return app(Authorizer::class)->allows($this->user(), $permission);
    }

    /**
     * @param  callable(): string  $callback
     */
    protected function run(callable $callback): void
    {
        try {
            $message = $callback();
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        $this->updatedOfferingId();
        Notification::make()->success()->title($message)->send();
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
