<?php

namespace App\Filament\Resources\Routine\Pages;

use App\Enums\CourseType;
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
use Illuminate\Support\Str;

class ManageRoutine extends Page
{
    use InteractsWithRecord;

    protected static string $resource = RoutineResource::class;

    protected string $view = 'filament.routine.manage-routine';

    /** @var array<int, array<int, array{slot_id: int, course_id: int, teacher_id: int, course_code: string, course_name: string, course_type: string, teacher_name: string, teacher_short: string, slot_group_id: ?string, lab_span: int}>> */
    public array $assignments = [];

    /** @var array<int, array<int, array<string, string>>> */
    public array $options = [];

    /** @var array<int, string> */
    public array $dayNames = RoutineSlot::DAYS;

    /** Tracks which [day][time_slot_id] are lab continuation slots (should be skipped in rendering). */
    public array $labContinuations = [];

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

                    $skippedCount = count($result['skipped']);
                    $message = "{$result['scheduled']} slots scheduled".($skippedCount > 0 ? ", {$skippedCount} courses skipped" : '');

                    Notification::make()
                        ->title($message)
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

    public function clearSlotAction(): Action
    {
        return Action::make('clearSlot')
            ->label('Remove')
            ->color('danger')
            ->size('sm')
            ->link()
            ->icon(Heroicon::OutlinedXMark)
            ->requiresConfirmation()
            ->modalHeading('Remove Assignment')
            ->modalDescription('Are you sure you want to remove this slot assignment?')
            ->modalSubmitActionLabel('Yes, Remove')
            ->action(function (array $arguments): void {
                $this->clearSlot($arguments['day'], $arguments['timeSlotId']);

                Notification::make()
                    ->title('Slot removed')
                    ->success()
                    ->send();
            });
    }

    public function timeSlots(): \Illuminate\Database\Eloquent\Collection
    {
        return TimeSlot::orderBy('sort_order')->get();
    }

    private function loadRoutine(): void
    {
        /** @var Batch $batch */
        $batch = $this->record;

        $allBatchSlots = RoutineSlot::where('batch_id', $batch->id)
            ->with(['course', 'teacher.user', 'timeSlot'])
            ->get();

        $slots = $allBatchSlots->where('is_lab_continuation', false);
        $continuations = $allBatchSlots->where('is_lab_continuation', true);

        $this->assignments = [];
        $this->labContinuations = [];

        foreach ($continuations as $cont) {
            $this->labContinuations[$cont->day_of_week][$cont->time_slot_id] = true;
        }

        foreach ($slots as $slot) {
            $labSpan = 1;

            if ($slot->slot_group_id) {
                $labSpan = RoutineSlot::where('slot_group_id', $slot->slot_group_id)->count();
            }

            $this->assignments[$slot->day_of_week][$slot->time_slot_id] = [
                'slot_id' => $slot->id,
                'course_id' => $slot->course_id,
                'teacher_id' => $slot->teacher_id,
                'course_code' => $slot->course->code,
                'course_name' => $slot->course->name,
                'course_type' => $slot->course->type->label(),
                'teacher_name' => $slot->teacher->user->name,
                'teacher_short' => $slot->teacher->short_name ?? $slot->teacher->user->name,
                'slot_group_id' => $slot->slot_group_id,
                'lab_span' => $labSpan,
            ];
        }

        $this->buildOptions();
    }

    private function buildOptions(): void
    {
        /** @var Batch $batch */
        $batch = $this->record;

        $allSlots = RoutineSlot::select('id', 'teacher_id', 'batch_id', 'day_of_week', 'time_slot_id', 'is_lab_continuation')->get();

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
                // Slots that are lab continuations can't be assigned directly
                $isContinuation = $allSlots
                    ->where('batch_id', $batch->id)
                    ->where('day_of_week', $day)
                    ->where('time_slot_id', $slot->id)
                    ->where('is_lab_continuation', true)
                    ->isNotEmpty();

                if ($isContinuation) {
                    $this->options[$day][$slot->id] = [];

                    continue;
                }

                $currentAssignment = $this->assignments[$day][$slot->id] ?? null;
                $currentSlotId = $currentAssignment ? $currentAssignment['slot_id'] : null;

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

                $busyTeacherIds = $allSlots
                    ->where('day_of_week', $day)
                    ->where('time_slot_id', $slot->id)
                    ->reject(fn ($r) => $r->id === $currentSlotId)
                    ->pluck('teacher_id')
                    ->unique();

                $opts = [];

                foreach ($courses as $course) {
                    // Lab courses can go in theory slots (3-in-a-row) or dedicated lab slots
                    // Theory courses only go in theory slots
                    $slotCompatible = $course->type === $slot->type
                        || ($course->type === CourseType::Lab && $slot->type === CourseType::Theory);

                    if (! $slotCompatible) {
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

        $course = Course::find($courseId);
        $timeSlot = TimeSlot::find($timeSlotId);

        // Lab course assigned to a theory slot → 3-in-a-row
        if ($course->type === CourseType::Lab && $timeSlot->type === CourseType::Theory) {
            $this->assignLabSpan($batch, $day, $timeSlot, (int) $courseId, (int) $teacherId);

            return;
        }

        // Standard single-slot assignment
        $batchBusy = RoutineSlot::where('batch_id', $batch->id)
            ->where('day_of_week', $day)
            ->where('time_slot_id', $timeSlotId)
            ->exists();

        if ($batchBusy) {
            Notification::make()->warning()->title('This time slot is already occupied for this batch.')->send();
            $this->loadRoutine();

            return;
        }

        $teacherBusy = RoutineSlot::where('teacher_id', $teacherId)
            ->where('day_of_week', $day)
            ->where('time_slot_id', $timeSlotId)
            ->exists();

        if ($teacherBusy) {
            Notification::make()->warning()->title('This teacher is already assigned at this time.')->send();
            $this->loadRoutine();

            return;
        }

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

    private function assignLabSpan(Batch $batch, int $day, TimeSlot $startSlot, int $courseId, int $teacherId): void
    {
        $theorySlots = TimeSlot::where('type', CourseType::Theory)
            ->orderBy('sort_order')
            ->get();

        $startIndex = $theorySlots->search(fn ($s) => $s->id === $startSlot->id);

        if ($startIndex === false || $startIndex + 2 >= $theorySlots->count()) {
            Notification::make()->warning()->title('Not enough consecutive theory slots available for lab (need 3 in a row).')->send();

            return;
        }

        $spanSlots = $theorySlots->slice($startIndex, 3)->values();

        foreach ($spanSlots as $slot) {
            $batchBusy = RoutineSlot::where('batch_id', $batch->id)
                ->where('day_of_week', $day)
                ->where('time_slot_id', $slot->id)
                ->exists();

            if ($batchBusy) {
                Notification::make()->warning()->title("Slot {$slot->name} is already occupied for this batch.")->send();

                return;
            }

            $teacherBusy = RoutineSlot::where('teacher_id', $teacherId)
                ->where('day_of_week', $day)
                ->where('time_slot_id', $slot->id)
                ->exists();

            if ($teacherBusy) {
                Notification::make()->warning()->title("Teacher is already assigned during {$slot->name}.")->send();

                return;
            }
        }

        $groupId = Str::ulid()->toString();

        foreach ($spanSlots as $index => $slot) {
            RoutineSlot::create([
                'department_id' => $batch->department_id,
                'batch_id' => $batch->id,
                'semester_number' => $batch->current_semester,
                'day_of_week' => $day,
                'time_slot_id' => $slot->id,
                'course_id' => $courseId,
                'teacher_id' => $teacherId,
                'slot_group_id' => $groupId,
                'is_lab_continuation' => $index > 0,
            ]);
        }

        Notification::make()->success()->title('Lab assigned across 3 consecutive slots.')->send();
        $this->loadRoutine();
    }

    public function clearSlot(int $day, int $timeSlotId): void
    {
        /** @var Batch $batch */
        $batch = $this->record;

        $slot = RoutineSlot::where('batch_id', $batch->id)
            ->where('day_of_week', $day)
            ->where('time_slot_id', $timeSlotId)
            ->first();

        if (! $slot) {
            return;
        }

        if ($slot->slot_group_id) {
            RoutineSlot::where('slot_group_id', $slot->slot_group_id)->delete();
        } else {
            $slot->delete();
        }

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
