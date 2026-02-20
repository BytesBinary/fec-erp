<?php

namespace App\Filament\Resources\ExamDuties\Pages;

use App\Filament\Resources\ExamDuties\ExamDutyResource;
use App\Models\Course;
use App\Models\ExamDuty;
use App\Models\ExamHall;
use App\Models\InstitutionSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ViewExamDuty extends Page
{
    protected static string $resource = ExamDutyResource::class;

    protected string $view = 'filament.resources.exam-duties.pages.view-exam-duty';

    /** @var array<string, mixed> */
    public array $examDuty = [];

    public function mount(int|string $record): void
    {
        $duty = ExamDuty::findOrFail($record);

        // duty_details may be double-encoded (stored as JSON string inside JSON)
        $rawDetails = $duty->duty_details;
        if (is_string($rawDetails)) {
            $rawDetails = json_decode($rawDetails, true) ?? [];
        }

        // Structure is UUID-keyed object, so we use array_values to iterate
        $resolved = collect(array_values($rawDetails))->map(function (array $detail): array {
            return [
                'date' => Carbon::parse($detail['date'])->format('d M Y'),
                'invigilator' => User::whereIn('id', $detail['invigilator'] ?? [])->pluck('name')->toArray(),
                'supervisor' => User::whereIn('id', $detail['supervisor'] ?? [])->pluck('name')->toArray(),
                'course' => Course::whereIn('id', $detail['course'] ?? [])->get()->map(fn ($c) => "{$c->code}: {$c->name}")->toArray(),
                'exam_hall' => ExamHall::whereIn('id', $detail['exam_hall'] ?? [])->pluck('name')->toArray(),
            ];
        })->toArray();

        $this->examDuty = [
            'exam_name' => $duty->exam_name,
            'exam_year' => $duty->exam_year,
            'start_time' => $duty->start_time,
            'end_time' => $duty->end_time,
            'duty_details' => $resolved,
        ];
    }

    public function getTitle(): string
    {
        return "Exam Duty: {$this->examDuty['exam_name']}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->action('download'),
        ];
    }

    public function download(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $pdf = Pdf::loadView('filament.manage-exams.download-exam-invigilator', [
            'examDuty' => $this->examDuty,
            'setting' => InstitutionSetting::current(),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "invigilator-{$this->examDuty['exam_name']}-{$this->examDuty['exam_year']}.pdf"
        );
    }
}
