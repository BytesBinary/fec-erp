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
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class MasterRoutineReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Routine';

    protected static ?string $navigationLabel = 'Master Routine';

    protected static ?string $title = 'Master Routine Report';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.master-routine-report';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<int, TimeSlot> */
    public array $timeSlots = [];

    /** @var array<int, string> */
    public array $days = RoutineSlot::DAYS;

    /**
     * Rows keyed by "dept_id|semester_number", each row has [day][time_slot_id] => {course_code, teacher_short}
     *
     * @var array<string, array{dept_name: string, dept_code: string, semester: int, slots: array<int, array<int, array{course_code: string, teacher_short: string, lab_span: int}>>}>
     */
    public array $reportRows = [];

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
                        Select::make('batch_numbers')
                            ->label('Batch Numbers')
                            ->multiple()
                            ->options(
                                Batch::where('is_archived', false)
                                    ->where('is_active', true)
                                    ->distinct()
                                    ->orderBy('batch_number')
                                    ->pluck('batch_number', 'batch_number')
                                    ->toArray()
                            )
                            ->placeholder('All active batches'),

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
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $state = $this->form->getState();

        $batchNumbers = $state['batch_numbers'] ?? [];
        $departmentIds = $state['department_ids'] ?? [];

        $batchQuery = Batch::where('is_archived', false)->where('is_active', true);

        if (! empty($batchNumbers)) {
            $batchQuery->whereIn('batch_number', $batchNumbers);
        }

        if (! empty($departmentIds)) {
            $batchQuery->whereIn('department_id', $departmentIds);
        }

        $batches = $batchQuery->with('department')->get();

        if ($batches->isEmpty()) {
            Notification::make()->warning()->title('No active batches found for selected filters.')->send();
            $this->hasResults = false;

            return;
        }

        $batchIds = $batches->pluck('id')->toArray();

        $slots = RoutineSlot::whereIn('batch_id', $batchIds)
            ->where('is_lab_continuation', false)
            ->with(['course', 'teacher', 'timeSlot', 'batch.department'])
            ->get();

        $this->reportRows = [];

        foreach ($batches as $batch) {
            $key = "{$batch->department_id}|{$batch->current_semester}";

            if (! isset($this->reportRows[$key])) {
                $this->reportRows[$key] = [
                    'dept_name' => $batch->department->name,
                    'dept_code' => $batch->department->code,
                    'semester' => $batch->current_semester,
                    'slots' => [],
                ];
            }
        }

        foreach ($slots as $slot) {
            $key = "{$slot->batch->department_id}|{$slot->semester_number}";

            if (! isset($this->reportRows[$key])) {
                continue;
            }

            $labSpan = $slot->slot_group_id
                ? RoutineSlot::where('slot_group_id', $slot->slot_group_id)->count()
                : 1;

            $this->reportRows[$key]['slots'][$slot->day_of_week][$slot->time_slot_id] = [
                'course_code' => $slot->course->code,
                'teacher_short' => $slot->teacher->short_name ?? $slot->teacher->user->name,
                'lab_span' => $labSpan,
            ];
        }

        // Remove rows where no slots are assigned
        $this->reportRows = array_filter(
            $this->reportRows,
            fn ($row) => ! empty($row['slots'])
        );

        // Sort: department name, then semester
        uasort($this->reportRows, fn ($a, $b) => $a['dept_name'] <=> $b['dept_name'] ?: $a['semester'] <=> $b['semester']);

        $this->hasResults = ! empty($this->reportRows);

        if (! $this->hasResults) {
            Notification::make()->warning()->title('No routine assignments found.')->send();
        }
    }

    public function download(): StreamedResponse
    {
        if (! $this->hasResults) {
            Notification::make()->warning()->title('Generate the report first.')->send();
        }

        $pdf = Pdf::loadView('filament.routine.master-routine-pdf', [
            'reportRows' => $this->reportRows,
            'timeSlots' => $this->timeSlots,
            'days' => $this->days,
            'setting' => InstitutionSetting::current(),
        ])->setPaper('a3', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'master-routine-'.now()->format('Y-m-d').'.pdf',
        );
    }

    public function downloadExcel(): StreamedResponse
    {
        if (! $this->hasResults) {
            Notification::make()->warning()->title('Generate the report first.')->send();
        }

        $xml = view('filament.routine.master-routine-excel', [
            'reportRows' => $this->reportRows,
            'timeSlots' => $this->timeSlots,
            'days' => $this->days,
        ])->render();

        return response()->streamDownload(
            fn () => print ($xml),
            'master-routine-'.now()->format('Y-m-d').'.xls',
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
