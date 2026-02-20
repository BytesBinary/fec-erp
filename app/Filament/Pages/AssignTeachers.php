<?php

namespace App\Filament\Pages;

use App\Models\Course;
use App\Models\Department;
use App\Models\Teacher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AssignTeachers extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Routine';

    protected static ?string $navigationLabel = 'Assign Teachers';

    protected static ?string $title = 'Assign Teachers to Courses';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.assign-teachers';

    public ?array $data = [];

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
                            ->live()
                            ->afterStateUpdated(fn (callable $set, callable $get) => $this->populateRepeater($set, $get)),

                        Select::make('semester')
                            ->label('Semester')
                            ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8))))
                            ->live()
                            ->afterStateUpdated(fn (callable $set, callable $get) => $this->populateRepeater($set, $get)),
                    ]),

                Section::make('Assignments')
                    ->schema([
                        Repeater::make('assignments')
                            ->label('Courses & Teachers')
                            ->hidden(fn ($get): bool => ! ($get('department_id') && $get('semester')))
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                Hidden::make('course_id'),
                                Grid::make(3)->schema([
                                    TextInput::make('course_code')
                                        ->label('Code')
                                        ->disabled()
                                        ->dehydrated(false),
                                    TextInput::make('course_name')
                                        ->label('Course')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(2),
                                ]),
                                Select::make('teacher_ids')
                                    ->label('Assigned Teachers')
                                    ->multiple()
                                    ->options(function (callable $get): array {
                                        $deptId = $get('../../department_id');

                                        if (! $deptId) {
                                            return [];
                                        }

                                        return Teacher::where('department_id', $deptId)
                                            ->with('user')
                                            ->get()
                                            ->mapWithKeys(fn (Teacher $t) => [$t->id => "{$t->user->name} ({$t->short_name})"])
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload(false),
                            ])
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    private function populateRepeater(callable $set, callable $get): void
    {
        $deptId = $get('department_id');
        $semester = $get('semester');

        if (! $deptId || ! $semester) {
            $set('assignments', []);

            return;
        }

        $courses = Course::where('department_id', $deptId)
            ->where('semester_number', $semester)
            ->where('is_active', true)
            ->with('teachers')
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $set('assignments', $courses->map(fn ($c) => [
            'course_id' => $c->id,
            'course_code' => $c->code,
            'course_name' => $c->name,
            'teacher_ids' => $c->teachers->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
        ])->toArray());
    }

    public function save(): void
    {
        if ($this->canSave() === false) {
            Notification::make()
                ->danger()
                ->title('Please choose a Department and Semester before saving.')
                ->send();

            return;
        }

        $state = $this->form->getState();

        foreach ($state['assignments'] as $item) {
            Course::find($item['course_id'])?->teachers()->sync($item['teacher_ids'] ?? []);
        }

        Notification::make()
            ->success()
            ->title('Assignments saved.')
            ->send();
    }

    public function canSave(): bool
    {
        $state = $this->form->getState();

        return ! empty($state['department_id']) && ! empty($state['semester']);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save')
                ->formId('data')
                ->disabled(! $this->canSave()),
        ];
    }
}
