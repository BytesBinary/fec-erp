<?php

namespace App\Services;

use App\Enums\CourseType;
use App\Models\Batch;
use App\Models\Course;
use App\Models\RoutineSlot;
use App\Models\TimeSlot;

class RoutineGeneratorService
{
    /**
     * Generate routine slots for a single batch's current semester.
     *
     * @return array{scheduled: int, skipped: list<string>}
     */
    public function generateForBatch(Batch $batch): array
    {
        $courses = Course::where('department_id', $batch->department_id)
            ->where('semester_number', $batch->current_semester)
            ->where('is_active', true)
            ->with('teachers.user')
            ->get();

        $theorySlots = TimeSlot::where('type', CourseType::Theory)->orderBy('sort_order')->get();
        $labSlots = TimeSlot::where('type', CourseType::Lab)->orderBy('sort_order')->get();

        // Clean regenerate
        RoutineSlot::where('batch_id', $batch->id)
            ->where('semester_number', $batch->current_semester)
            ->delete();

        // Pre-load global teacher busy map: [teacher_id][day][time_slot_id] = true
        /** @var array<int, array<int, array<int, bool>>> $teacherBusy */
        $teacherBusy = [];
        $allSlots = RoutineSlot::select('teacher_id', 'day_of_week', 'time_slot_id')->get();

        foreach ($allSlots as $slot) {
            $teacherBusy[$slot->teacher_id][$slot->day_of_week][$slot->time_slot_id] = true;
        }

        // Batch busy map for slots added during this run: [day][time_slot_id] = true
        /** @var array<int, array<int, bool>> $batchBusy */
        $batchBusy = [];

        // Theory day-assignment tracker to avoid double theory same day: [course_id][day] = true
        /** @var array<int, array<int, bool>> $courseDay */
        $courseDay = [];

        $scheduled = 0;
        $skipped = [];
        $days = [0, 1, 2, 3, 4];

        foreach ($courses as $course) {
            if ($course->teachers->isEmpty()) {
                $skipped[] = "{$course->code}: no teachers assigned";

                continue;
            }

            $target = $course->weekly_classes ?? (int) ceil($course->credit_hours) ?? 1;
            $slots = $course->type === CourseType::Lab ? $labSlots : $theorySlots;
            $classesScheduled = 0;

            $shuffledDays = $days;
            shuffle($shuffledDays);

            foreach ($shuffledDays as $day) {
                if ($classesScheduled >= $target) {
                    break;
                }

                $shuffledSlots = $slots->shuffle();

                foreach ($shuffledSlots as $slot) {
                    if ($classesScheduled >= $target) {
                        break;
                    }

                    // Skip if batch is busy at this slot
                    if (isset($batchBusy[$day][$slot->id])) {
                        continue;
                    }

                    // For theory courses: avoid scheduling same course twice on same day
                    if ($course->type === CourseType::Theory && isset($courseDay[$course->id][$day])) {
                        continue;
                    }

                    // Find first free teacher
                    $assignedTeacher = null;

                    foreach ($course->teachers as $teacher) {
                        if (! isset($teacherBusy[$teacher->id][$day][$slot->id])) {
                            $assignedTeacher = $teacher;
                            break;
                        }
                    }

                    if (! $assignedTeacher) {
                        continue;
                    }

                    RoutineSlot::create([
                        'department_id' => $batch->department_id,
                        'batch_id' => $batch->id,
                        'semester_number' => $batch->current_semester,
                        'day_of_week' => $day,
                        'time_slot_id' => $slot->id,
                        'course_id' => $course->id,
                        'teacher_id' => $assignedTeacher->id,
                    ]);

                    $teacherBusy[$assignedTeacher->id][$day][$slot->id] = true;
                    $batchBusy[$day][$slot->id] = true;
                    $courseDay[$course->id][$day] = true;
                    $classesScheduled++;
                    $scheduled++;
                }
            }

            if ($classesScheduled < $target) {
                $skipped[] = "{$course->code}: only {$classesScheduled}/{$target} slots scheduled";
            }
        }

        return [
            'scheduled' => $scheduled,
            'skipped' => $skipped,
        ];
    }

    /**
     * Generate routines for all active batches.
     *
     * @return array{batches_processed: int, scheduled: int, skipped: list<string>}
     */
    public function generateForAll(): array
    {
        $batches = Batch::where('is_active', true)->with('department')->get();

        $totalScheduled = 0;
        $allSkipped = [];

        foreach ($batches as $batch) {
            $result = $this->generateForBatch($batch);
            $totalScheduled += $result['scheduled'];

            foreach ($result['skipped'] as $message) {
                $allSkipped[] = "[{$batch->department->code} B{$batch->batch_number}] {$message}";
            }
        }

        return [
            'batches_processed' => $batches->count(),
            'scheduled' => $totalScheduled,
            'skipped' => $allSkipped,
        ];
    }
}
