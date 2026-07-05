<?php

namespace App\Filament\Pages;

use App\Models\InstitutionSetting;
use App\Models\RoutineSlot;
use App\Models\Teacher;
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
use Symfony\Component\HttpFoundation\StreamedResponse;
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
     * Same structure as MasterRoutineReport::$reportRows, filtered to one teacher.
     *
     * @var array<string, array{dept_name: string, dept_code: string, semester: int, slots: array<int, array<int, array{course_code: string, teacher_short: string, lab_span: int}>>}>
     */
    public array $reportRows = [];

    public string $teacherName = '';

    public string $teacherShort = '';

    public string $teacherDept = '';

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
                Section::make('Select Teacher')
                    ->schema([
                        Select::make('teacher_id')
                            ->label('Teacher')
                            ->searchable()
                            ->required()
                            ->options(function (): array {
                                return Teacher::with(['user', 'department'])
                                    ->get()
                                    ->sortBy('user.name')
                                    ->mapWithKeys(fn (Teacher $t) => [
                                        $t->id => $t->user->name.($t->short_name ? ' ('.$t->short_name.')' : ''),
                                    ])
                                    ->toArray();
                            })
                            ->placeholder('Search teacher name...'),
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $state = $this->form->getState();
        $teacherId = $state['teacher_id'] ?? null;

        if (! $teacherId) {
            Notification::make()->warning()->title('Select a teacher first.')->send();

            return;
        }

        $teacher = Teacher::with(['user', 'department'])->find($teacherId);

        if (! $teacher) {
            Notification::make()->warning()->title('Teacher not found.')->send();

            return;
        }

        $this->teacherName = $teacher->user->name;
        $this->teacherShort = $teacher->short_name ?? $teacher->user->name;
        $this->teacherDept = $teacher->department->code ?? '';

        $slots = RoutineSlot::where('teacher_id', $teacherId)
            ->where('is_lab_continuation', false)
            ->with(['course', 'teacher', 'timeSlot', 'batch.department'])
            ->get();

        $this->reportRows = [];

        foreach ($slots as $slot) {
            $key = "{$slot->batch->department_id}|{$slot->semester_number}";

            if (! isset($this->reportRows[$key])) {
                $this->reportRows[$key] = [
                    'dept_name' => $slot->batch->department->name,
                    'dept_code' => $slot->batch->department->code,
                    'semester' => $slot->semester_number,
                    'slots' => [],
                ];
            }

            $labSpan = $slot->slot_group_id
                ? RoutineSlot::where('slot_group_id', $slot->slot_group_id)->count()
                : 1;

            $this->reportRows[$key]['slots'][$slot->day_of_week][$slot->time_slot_id] = [
                'course_code' => $slot->course->code,
                'teacher_short' => $this->teacherShort,
                'lab_span' => $labSpan,
            ];
        }

        // Sort: department name, then semester
        uasort($this->reportRows, fn ($a, $b) => $a['dept_name'] <=> $b['dept_name'] ?: $a['semester'] <=> $b['semester']);

        $this->hasResults = ! empty($this->reportRows);

        if (! $this->hasResults) {
            Notification::make()->warning()->title('No routine assignments found for this teacher.')->send();
        }
    }

    public function download(): StreamedResponse
    {
        if (! $this->hasResults) {
            Notification::make()->warning()->title('Generate the report first.')->send();
        }

        $pdf = Pdf::loadView('filament.routine.individual-routine-pdf', [
            'reportRows' => $this->reportRows,
            'timeSlots' => $this->timeSlots,
            'days' => $this->days,
            'setting' => InstitutionSetting::current(),
            'teacherName' => $this->teacherName,
            'teacherShort' => $this->teacherShort,
            'teacherDept' => $this->teacherDept,
        ])->setPaper('a3', 'landscape');

        $filename = 'routine-'.str($this->teacherShort)->slug().'-'.now()->format('Y-m-d').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
        );
    }

    public function downloadExcel(): StreamedResponse
    {
        if (! $this->hasResults) {
            Notification::make()->warning()->title('Generate the report first.')->send();
        }

        $xml = view('filament.routine.individual-routine-excel', [
            'reportRows' => $this->reportRows,
            'timeSlots' => $this->timeSlots,
            'days' => $this->days,
            'teacherName' => $this->teacherName,
            'teacherShort' => $this->teacherShort,
            'teacherDept' => $this->teacherDept,
        ])->render();

        $filename = 'routine-'.str($this->teacherShort)->slug().'-'.now()->format('Y-m-d').'.xls';

        return response()->streamDownload(
            fn () => print ($xml),
            $filename,
            ['Content-Type' => 'application/vnd.ms-excel; charset=utf-8'],
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

            Action::make('downloadExcel')
                ->label('Download Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('info')
                ->action('downloadExcel')
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
