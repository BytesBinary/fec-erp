<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\InstitutionSetting;
use App\Models\RoutineSlot;
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

class CreditCountReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Routine';

    protected static ?string $navigationLabel = 'Credit Count Report';

    protected static ?string $title = 'Credit Count Report';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.credit-count-report';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<int, array{teacher_name: string, teacher_short: string, courses: array<int, array{code: string, name: string, credit_hours: float}>, total_credits: float}> */
    public array $results = [];

    public ?string $departmentName = null;

    public ?string $semesterLabel = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Select Department & Semester')
                    ->columns(2)
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->options(
                                Department::where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('semester')
                            ->label('Semester')
                            ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8))))
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $state = $this->form->getState();

        $departmentId = $state['department_id'] ?? null;
        $semester = $state['semester'] ?? null;

        if (! $departmentId || ! $semester) {
            return;
        }

        // Distinct teacher+course pairs — avoids double-counting the same course
        // taught multiple times a week by the same teacher
        $slots = RoutineSlot::query()
            ->where('department_id', $departmentId)
            ->where('semester_number', $semester)
            ->select('teacher_id', 'course_id')
            ->distinct()
            ->with(['teacher.user', 'course'])
            ->get();

        $this->results = $slots
            ->groupBy('teacher_id')
            ->map(function ($teacherSlots) {
                $teacher = $teacherSlots->first()->teacher;

                return [
                    'teacher_name' => $teacher->user->name,
                    'teacher_short' => $teacher->short_name ?? $teacher->user->name,
                    'courses' => $teacherSlots->map(fn ($s) => [
                        'code' => $s->course->code,
                        'name' => $s->course->name,
                        'credit_hours' => (float) $s->course->credit_hours,
                    ])->values()->toArray(),
                    'total_credits' => (float) $teacherSlots->sum(fn ($s) => $s->course->credit_hours),
                ];
            })
            ->sortByDesc('total_credits')
            ->values()
            ->toArray();

        $this->departmentName = Department::find($departmentId)?->name;
        $this->semesterLabel = "Semester {$semester}";
    }

    public function download(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (empty($this->results)) {
            Notification::make()->warning()->title('Generate a report first.')->send();
        }

        $pdf = Pdf::loadView('filament.reports.download-credit-count', [
            'results' => $this->results,
            'departmentName' => $this->departmentName,
            'semesterLabel' => $this->semesterLabel,
            'setting' => InstitutionSetting::current(),
        ])->setPaper('a4', 'portrait');

        $slug = str($this->departmentName ?? 'dept')->slug();

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "credit-count-{$slug}-{$this->semesterLabel}.pdf",
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
                ->disabled(fn (): bool => empty($this->results)),
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
