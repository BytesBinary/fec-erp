<?php

namespace App\Filament\Pages;

use App\Models\Batch;
use App\Models\Department;
use App\Models\InstitutionSetting;
use App\Models\RoutineSlot;
use App\Models\TimeSlot;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class IndividualRoutineReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Routine';

    protected static ?string $navigationLabel = 'Individual Routine';

    protected static ?string $title = 'Individual Routine Report';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.individual-routine-report';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<int, TimeSlot> */
    public array $timeSlots = [];

    /** @var array<int, string> */
    public array $days = RoutineSlot::DAYS;

    /**
     * Teacher-keyed rows: [teacher_id] => {name, short, dept_code, slots: [day][time_slot_id] => {course_code, dept_code, semester}}
     *
     * @var array<int, array{name: string, short: string, dept_code: string, slots: array<int, array<int, array{course_code: string, dept_code: string, semester: int}>>}>
     */
    public array $teacherRows = [];

    public bool $hasResults = false;

    public function mount(): void
    {
        $this->timeSlots = TimeSlot::orderBy('sort_order')->get()->all();
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filters')
                    ->columns(2)
                    ->schema([
                        Select::make('department_ids')
                            ->label('Departments')
                            ->multiple()
                            ->options(
                                Department::where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->placeholder('All departments'),

                        Select::make('batch_ids')
                            ->label('Batches (optional)')
                            ->multiple()
                            ->options(function (): array {
                                return Batch::where('is_archived', false)
                                    ->where('is_active', true)
                                    ->with('department')
                                    ->orderBy('batch_number')
                                    ->get()
                                    ->mapWithKeys(fn (Batch $b) => [
                                        $b->id => "{$b->department->code} — Batch {$b->batch_number} (Sem {$b->current_semester})",
                                    ])
                                    ->toArray();
                            })
                            ->placeholder('All active batches'),
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $state = $this->form->getState();
        $batchIds = $state['batch_ids'] ?? [];
        $departmentIds = $state['department_ids'] ?? [];

        $batchQuery = Batch::where('is_archived', false)->where('is_active', true);

        if (! empty($batchIds)) {
            $batchQuery->whereIn('id', $batchIds);
        }

        if (! empty($departmentIds)) {
            $batchQuery->whereIn('department_id', $departmentIds);
        }

        $activeBatchIds = $batchQuery->pluck('id')->toArray();

        if (empty($activeBatchIds)) {
            Notification::make()->warning()->title('No active batches found.')->send();
            $this->hasResults = false;

            return;
        }

        $slots = RoutineSlot::whereIn('batch_id', $activeBatchIds)
            ->where('is_lab_continuation', false)
            ->with(['course', 'teacher.user', 'teacher.department', 'timeSlot', 'batch.department'])
            ->get();

        $this->teacherRows = [];

        foreach ($slots as $slot) {
            $teacherId = $slot->teacher_id;
            $teacher = $slot->teacher;

            if (! isset($this->teacherRows[$teacherId])) {
                $this->teacherRows[$teacherId] = [
                    'name' => $teacher->user->name,
                    'short' => $teacher->short_name ?? $teacher->user->name,
                    'dept_code' => $teacher->department->code ?? '',
                    'slots' => [],
                ];
            }

            $this->teacherRows[$teacherId]['slots'][$slot->day_of_week][$slot->time_slot_id] = [
                'course_code' => $slot->course->code,
                'dept_code' => $slot->batch->department->code,
                'semester' => $slot->semester_number,
            ];
        }

        // Sort by teacher name
        uasort($this->teacherRows, fn ($a, $b) => $a['name'] <=> $b['name']);

        $this->hasResults = ! empty($this->teacherRows);

        if (! $this->hasResults) {
            Notification::make()->warning()->title('No routine assignments found.')->send();
        }
    }

    public function download(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! $this->hasResults) {
            Notification::make()->warning()->title('Generate the report first.')->send();
        }

        $pdf = Pdf::loadView('filament.routine.individual-routine-pdf', [
            'teacherRows' => $this->teacherRows,
            'timeSlots' => $this->timeSlots,
            'days' => $this->days,
            'setting' => InstitutionSetting::current(),
        ])->setPaper('a3', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'individual-routine-'.now()->format('Y-m-d').'.pdf',
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->action('download')
                ->disabled(fn (): bool => ! $this->hasResults),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate Report')
                ->icon(Heroicon::OutlinedChartBar)
                ->submit('generate')
                ->formId('data'),
        ];
    }
}
