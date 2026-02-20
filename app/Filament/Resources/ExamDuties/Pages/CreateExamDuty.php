<?php

namespace App\Filament\Resources\ExamDuties\Pages;

use App\Filament\Resources\ExamDuties\ExamDutyResource;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Department;
use App\Models\ExamDuty;
use App\Models\ExamHall;
use App\Models\ExamType;
use App\Models\Teacher;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class CreateExamDuty extends Page
{
    use InteractsWithForms;

    protected static string $resource = ExamDutyResource::class;

    protected string $view = 'filament.exam-duties.create-exam-duty';

    public $exam_name;

    public $exam_type_id;

    public $semester;

    public $batch;

    public $department;

    public $exam_year;

    public $duty_details = [];

    public $start_time;

    public $end_time;

    public string $record = '';

    public function mount($record = ''): void
    {
        if (! empty($record)) {
            $this->record = $record;
            $data = ExamDuty::find($record)->toArray();
            $data['semester'] = json_decode($data['semester'], true);
            $data['batch'] = json_decode($data['batch'], true);
            $data['department'] = json_decode($data['department'], true);
            $data['duty_details'] = json_decode($data['duty_details'], true);
            $this->form->fill($data);
        }
    }

    public function createExamDuty(): void
    {
        $this->validate();

        ExamDuty::create([
            'exam_name' => $this->exam_name,
            'exam_type_id' => $this->exam_type_id,
            'semester' => json_encode($this->semester, true),
            'batch' => json_encode($this->batch, true),
            'department' => json_encode($this->department, true),
            'exam_year' => $this->exam_year,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duty_details' => json_encode($this->duty_details, true),
        ]);

        Notification::make()
            ->title('Exam Duty Created')
            ->success()
            ->send();

        redirect(ExamDutyResource::getUrl('index'));
    }

    public function updateExamDuty()
    {
        $this->validate();

        ExamDuty::find($this->record)->update([
            'exam_name' => $this->exam_name,
            'exam_type_id' => $this->exam_type_id,
            'semester' => json_encode($this->semester, true),
            'batch' => json_encode($this->batch, true),
            'department' => json_encode($this->department, true),
            'exam_year' => $this->exam_year,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duty_details' => json_encode($this->duty_details, true),
        ]);

        Notification::make()
            ->title('Exam Duty Updated')
            ->success()
            ->send();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                // Main fields grid — 3 columns on large screens
                Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])
                    ->schema([
                        TextInput::make('exam_name')
                            ->label('Exam Name')
                            ->placeholder('Enter an exam name')
                            ->required()
                            ->statePath('exam_name'),

                        Select::make('exam_type_id')
                            ->label('Exam Type')
                            ->options(ExamType::pluck('type', 'id')->toArray())
                            ->required()
                            ->statePath('exam_type_id'),

                        Select::make('semester')
                            ->label('Semester')
                            ->multiple()
                            ->options([
                                '1' => '1st Semester',
                                '2' => '2nd Semester',
                                '3' => '3rd Semester',
                                '4' => '4th Semester',
                                '5' => '5th Semester',
                                '6' => '6th Semester',
                                '7' => '7th Semester',
                                '8' => '8th Semester',
                            ])
                            ->reactive()
                            ->required()
                            ->statePath('semester'),

                        Select::make('batch')
                            ->label('Batch')
                            ->multiple()
                            ->options(Batch::pluck('batch_number', 'id')->toArray())
                            ->required()
                            ->statePath('batch'),

                        Select::make('department')
                            ->label('Department')
                            ->multiple()
                            ->reactive()
                            ->options(Department::pluck('code', 'id')->toArray())
                            ->required()
                            ->statePath('department'),

                        TextInput::make('exam_year')
                            ->label('Exam Year')
                            ->required()
                            ->statePath('exam_year'),

                        TimePicker::make('start_time')
                            ->label('Start Time')
                            ->required()
                            ->statePath('start_time'),

                        TimePicker::make('end_time')
                            ->label('End Time')
                            ->required()
                            ->statePath('end_time'),
                    ]),

                // Repeater for duty details
                Repeater::make('duty_details')
                    ->reactive()
                    ->schema([
                        Grid::make(5) // 5-column grid
                            ->schema([
                                DatePicker::make('date')
                                    ->label('Date')
                                    ->required(),

                                Select::make('exam_hall')
                                    ->label('Exam Hall')
                                    ->searchable()
                                    ->multiple()
                                    ->options(ExamHall::pluck('name', 'id')->toArray())
                                    ->required(),

                                Select::make('course')
                                    ->label('Course')
                                    ->searchable()
                                    ->multiple()
                                    ->options(function () {
                                        if (empty($this->department) || empty($this->semester)) {
                                            Notification::make()
                                                ->title('Please select department and semester first')
                                                ->warning()
                                                ->send();

                                            return [];
                                        }

                                        return Course::whereIn('department_id', $this->department)
                                            ->whereIn('semester_number', $this->semester)
                                            ->select(DB::raw("id, CONCAT(code, ' : ', name ) as title_code"))
                                            ->pluck('title_code', 'id')
                                            ->toArray();
                                    })
                                    ->required(),

                                Select::make('supervisor')
                                    ->label('Supervisor')
                                    ->searchable()
                                    ->multiple()
                                    ->reactive()
                                    ->options(User::whereIn('id', Teacher::pluck('user_id')->toArray())->pluck('name', 'id')->toArray())
                                    ->required(),

                                Select::make('invigilator')
                                    ->label('Invigilator')
                                    ->searchable()
                                    ->multiple()
                                    ->reactive()
                                    ->options(User::whereIn('id', Teacher::pluck('user_id')->toArray())->pluck('name', 'id')->toArray())
                                    ->required(),
                            ]),
                    ])
                    ->statePath('duty_details'),
            ]);
    }

    public function getFormActions($action = 'save'): array
    {
        $actions = [
            'save' => [
                Action::make('Save')
                    ->label('Save Exam Duty')
                    ->action('createExamDuty'),
            ],
            'update' => [
                Action::make('Update')
                    ->label('Update Exam Duty')
                    ->action('updateExamDuty'),
            ],
        ];

        return $actions[$action];
    }

    public function getTeacherDutyCounts(): array
    {
        $counts = [];

        foreach ($this->duty_details ?? [] as $detail) {
            foreach (['invigilator', 'supervisor'] as $role) {
                if (! empty($detail[$role]) && is_array($detail[$role])) {
                    foreach ($detail[$role] as $userId) {
                        if (! isset($counts[$userId])) {
                            $counts[$userId] = 0;
                        }
                        $counts[$userId]++;
                    }
                }
            }
        }

        return $counts;
    }
}
