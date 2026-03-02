<?php

namespace App\Filament\Resources\Routine\Pages;

use App\Filament\Resources\Routine\RoutineResource;
use App\Models\Batch;
use App\Models\Course;
use App\Models\InstitutionSetting;
use App\Models\RoutineSlot;
use App\Models\TimeSlot;
use App\Services\RoutineGeneratorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ManageRoutine extends Page
{
    use InteractsWithRecord;

    protected static string $resource = RoutineResource::class;

    protected string $view = 'filament.routine.manage-routine';

    /** @var array<int, array<int, array{slot_id: int, course_id: int, teacher_id: int, course_code: string, course_name: string, course_type: string, teacher_name: string, teacher_short: string}>> */
    public array $assignments = [];

    /** @var array<int, array<int, array<string, string>>> */
    public array $options = [];

    /** @var array<int, string> */
    public array $dayNames = RoutineSlot::DAYS;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->loadRoutine();
    }

    public function getTitle(): string
    {
        /** @var Batch $batch */
        $batch = $this->record;

        return "Routine — {$batch->department->code} Batch {$batch->batch_number} (Sem {$batch->current_semester})";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateRoutine')
                ->label('Generate Routine')
                ->icon(Heroicon::OutlinedBolt)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Auto-Generate Routine for This Batch')
                ->modalDescription('This will delete and regenerate all routine slots for this batch. Manual edits will be lost. Continue?')
                ->modalSubmitActionLabel('Yes, Generate')
                ->action(function (): void {
                    /** @var Batch $batch */
                    $batch = $this->record;

                    $result = app(RoutineGeneratorService::class)->generateForBatch($batch);

                    $this->loadRoutine();

                    $message = "Scheduled {$result['scheduled']} slots.";

                    if (count($result['skipped']) > 0) {
                        $message .= ' Skipped: '.implode(', ', $result['skipped']);
                    }

                    Notification::make()
                        ->title('Routine Generated')
                        ->body($message)
                        ->success()
                        ->send();
                }),
            Action::make('download')
                ->label('Download PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->action('download'),
        ];
    }

    public function timeSlots(): \Illuminate\Database\Eloquent\Collection
    {
        return TimeSlot::orderBy('sort_order')->get();
    }

    private function loadRoutine(): void
    {
        /** @var Batch $batch */
        $batch = $this->record;

        $slots = RoutineSlot::where('batch_id', $batch->id)
            ->with(['course', 'teacher.user', 'timeSlot'])
            ->get();

        $this->assignments = [];

        foreach ($slots as $slot) {
            $this->assignments[$slot->day_of_week][$slot->time_slot_id] = [
                'slot_id' => $slot->id,
                'course_id' => $slot->course_id,
                'teacher_id' => $slot->teacher_id,
                'course_code' => $slot->course->code,
                'course_name' => $slot->course->name,
                'course_type' => $slot->course->type->label(),
                'teacher_name' => $slot->teacher->user->name,
                'teacher_short' => $slot->teacher->short_name ?? $slot->teacher->user->name,
            ];
        }

        $this->buildOptions();
    }

    private function buildOptions(): void
    {
        /** @var Batch $batch */
        $batch = $this->record;

        // Build map of all existing assignments: [day][timeSlotId] => slot_id
        $allSlots = RoutineSlot::select('id', 'teacher_id', 'batch_id', 'day_of_week', 'time_slot_id')->get();

        // Courses for this batch's department + semester
        $courses = Course::where('department_id', $batch->department_id)
            ->where('semester_number', $batch->current_semester)
            ->where('is_active', true)
            ->with('teachers.user')
            ->get();

        $timeSlots = TimeSlot::orderBy('sort_order')->get();
        $days = array_keys(RoutineSlot::DAYS);

        $this->options = [];

        foreach ($days as $day) {
            foreach ($timeSlots as $slot) {
                $currentAssignment = $this->assignments[$day][$slot->id] ?? null;
                $currentSlotId = $currentAssignment ? $currentAssignment['slot_id'] : null;

                // Check if batch is already busy at this slot (excluding current assignment)
                $batchBusy = $allSlots
                    ->where('batch_id', $batch->id)
                    ->where('day_of_week', $day)
                    ->where('time_slot_id', $slot->id)
                    ->reject(fn ($r) => $r->id === $currentSlotId)
                    ->isNotEmpty();

                if ($batchBusy) {
                    $this->options[$day][$slot->id] = [];

                    continue;
                }

                // Busy teacher IDs at this slot (excluding current assignment)
                $busyTeacherIds = $allSlots
                    ->where('day_of_week', $day)
                    ->where('time_slot_id', $slot->id)
                    ->reject(fn ($r) => $r->id === $currentSlotId)
                    ->pluck('teacher_id')
                    ->unique();

                $opts = [];

                foreach ($courses as $course) {
                    // Only offer courses whose type matches the slot type
                    if ($course->type !== $slot->type) {
                        continue;
                    }

                    foreach ($course->teachers as $teacher) {
                        if ($busyTeacherIds->contains($teacher->id)) {
                            continue;
                        }

                        $shortName = $teacher->short_name ?? $teacher->user->name;
                        $opts["{$course->id}|{$teacher->id}"] = "{$course->code} — {$shortName}";
                    }
                }

                $this->options[$day][$slot->id] = $opts;
            }
        }
    }

    public function assignSlot(int $day, int $timeSlotId, string $value): void
    {
        if (empty($value)) {
            return;
        }

        /** @var Batch $batch */
        $batch = $this->record;

        [$courseId, $teacherId] = explode('|', $value);

        // Double-check batch slot is free
        $batchBusy = RoutineSlot::where('batch_id', $batch->id)
            ->where('day_of_week', $day)
            ->where('time_slot_id', $timeSlotId)
            ->exists();

        if ($batchBusy) {
            Notification::make()->warning()->title('This time slot is already occupied for this batch.')->send();
            $this->loadRoutine();

            return;
        }

        // Double-check teacher is free
        $teacherBusy = RoutineSlot::where('teacher_id', $teacherId)
            ->where('day_of_week', $day)
            ->where('time_slot_id', $timeSlotId)
            ->exists();

        if ($teacherBusy) {
            Notification::make()->warning()->title('This teacher is already assigned at this time.')->send();
            $this->loadRoutine();

            return;
        }

        $slot = TimeSlot::find($timeSlotId);
        $course = Course::find($courseId);

        RoutineSlot::create([
            'department_id' => $batch->department_id,
            'batch_id' => $batch->id,
            'semester_number' => $batch->current_semester,
            'day_of_week' => $day,
            'time_slot_id' => $timeSlotId,
            'course_id' => $courseId,
            'teacher_id' => $teacherId,
        ]);

        Notification::make()->success()->title('Slot assigned successfully.')->send();
        $this->loadRoutine();
    }

    public function clearSlot(int $day, int $timeSlotId): void
    {
        /** @var Batch $batch */
        $batch = $this->record;

        RoutineSlot::where('batch_id', $batch->id)
            ->where('day_of_week', $day)
            ->where('time_slot_id', $timeSlotId)
            ->delete();

        $this->loadRoutine();
    }

    public function download(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        /** @var Batch $batch */
        $batch = $this->record;
        $batch->load('department');

        $pdf = Pdf::loadView('filament.routine.download-routine', [
            'batch' => $batch,
            'days' => RoutineSlot::DAYS,
            'timeSlots' => TimeSlot::orderBy('sort_order')->get(),
            'assignments' => $this->assignments,
            'setting' => InstitutionSetting::current(),
        ])->setPaper('a4', 'landscape');

        $filename = "routine-{$batch->department->code}-batch{$batch->batch_number}-sem{$batch->current_semester}.pdf";

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
        );
    }
}
