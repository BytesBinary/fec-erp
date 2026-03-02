<?php

namespace App\Filament\Pages;

use App\Models\ExamDuty;
use App\Models\InstitutionSetting;
use App\Models\User;
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

class ExamDutyReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Manage Exams';

    protected static ?string $navigationLabel = 'Exam Duty Report';

    protected static ?string $title = 'Exam Duty Report';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.exam-duty-report';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<int, array{name: string, duties: int}> */
    public array $results = [];

    public ?string $examName = null;

    public ?string $examYear = null;

    public ?string $reportType = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Select Exam & Report Type')
                    ->columns(2)
                    ->schema([
                        Select::make('exam_duty_id')
                            ->label('Exam Duty')
                            ->options(
                                ExamDuty::orderByDesc('created_at')
                                    ->pluck('exam_name', 'id')
                                    ->toArray()
                            )
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('report_type')
                            ->label('Report Type')
                            ->options([
                                'invigilator' => 'Invigilator',
                                'supervisor' => 'Supervisor',
                            ])
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $state = $this->form->getState();

        $examDutyId = $state['exam_duty_id'] ?? null;
        $reportType = $state['report_type'] ?? null;

        if (! $examDutyId || ! $reportType) {
            return;
        }

        $examDuty = ExamDuty::find($examDutyId);

        if (! $examDuty) {
            Notification::make()->danger()->title('Exam duty not found.')->send();

            return;
        }

        $this->examName = $examDuty->exam_name;
        $this->examYear = $examDuty->exam_year;
        $this->reportType = $reportType;

        $dutyDetails = $examDuty->duty_details;

        // duty_details may be double-encoded (CreateExamDuty json_encodes manually
        // while the model cast also encodes), so decode again if needed.
        if (is_string($dutyDetails)) {
            $dutyDetails = json_decode($dutyDetails, true);
        }

        if (! is_array($dutyDetails) || empty($dutyDetails)) {
            $this->results = [];
            Notification::make()->warning()->title('No duty details found for this exam.')->send();

            return;
        }

        $counts = [];

        foreach ($dutyDetails as $detail) {
            $roleIds = $detail[$reportType] ?? [];

            if (! is_array($roleIds)) {
                continue;
            }

            foreach ($roleIds as $userId) {
                if (! isset($counts[$userId])) {
                    $counts[$userId] = 0;
                }
                $counts[$userId]++;
            }
        }

        if (empty($counts)) {
            $this->results = [];
            Notification::make()->warning()->title('No data found for the selected report type.')->send();

            return;
        }

        $users = User::whereIn('id', array_keys($counts))->pluck('name', 'id');

        $this->results = collect($counts)
            ->map(fn (int $dutyCount, int|string $userId) => [
                'name' => $users[$userId] ?? "Unknown (ID: {$userId})",
                'duties' => $dutyCount,
            ])
            ->sortByDesc('duties')
            ->values()
            ->toArray();
    }

    public function download(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (empty($this->results)) {
            Notification::make()->warning()->title('Generate a report first.')->send();
        }

        $pdf = Pdf::loadView('filament.reports.download-exam-duty-report', [
            'results' => $this->results,
            'examName' => $this->examName,
            'examYear' => $this->examYear,
            'reportType' => $this->reportType,
            'setting' => InstitutionSetting::current(),
        ])->setPaper('a4', 'portrait');

        $slug = str($this->examName ?? 'exam')->slug();

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "exam-duty-{$this->reportType}-{$slug}-{$this->examYear}.pdf",
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
