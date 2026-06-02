<?php

namespace App\Filament\Pages;

use App\Models\Batch;
use App\Models\Department;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class BatchOverview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Academic';

    protected static ?string $navigationLabel = 'Batches';

    protected static ?string $title = 'Batch Management';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.batch-overview';

    /** @var array<int, array{batch_number: int, session: string, dept_count: int}> */
    public array $batches = [];

    public function mount(): void
    {
        $this->loadBatches();
    }

    private function loadBatches(): void
    {
        $this->batches = Batch::query()
            ->where('is_archived', false)
            ->selectRaw('batch_number, MAX(session) as session, COUNT(*) as dept_count')
            ->groupBy('batch_number')
            ->orderBy('batch_number')
            ->get()
            ->toArray();
    }

    public function addBatchAction(): Action
    {
        return Action::make('addBatch')
            ->label('Add New Batch')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add New Batch')
            ->form([
                TextInput::make('batch_number')
                    ->label('Batch Number')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('session')
                    ->label('Academic Session')
                    ->placeholder('e.g. 2017-2018')
                    ->required()
                    ->maxLength(20),
                Select::make('department_id')
                    ->label('First Department')
                    ->options(Department::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('current_semester')
                    ->label('Starting Semester')
                    ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8))))
                    ->required()
                    ->default(1),
            ])
            ->action(function (array $data): void {
                $exists = Batch::where('batch_number', $data['batch_number'])
                    ->where('department_id', $data['department_id'])
                    ->exists();

                if ($exists) {
                    Notification::make()->warning()->title('This batch already exists for the selected department.')->send();

                    return;
                }

                Batch::create([
                    'batch_number' => $data['batch_number'],
                    'session' => $data['session'],
                    'department_id' => $data['department_id'],
                    'current_semester' => $data['current_semester'],
                    'is_active' => true,
                    'is_archived' => false,
                ]);

                $this->loadBatches();

                Notification::make()->success()->title('Batch created.')->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addBatch')
                ->label('Add New Batch')
                ->icon(Heroicon::OutlinedPlus)
                ->color('primary')
                ->modalHeading('Add New Batch')
                ->form([
                    TextInput::make('batch_number')
                        ->label('Batch Number')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                    TextInput::make('session')
                        ->label('Academic Session')
                        ->placeholder('e.g. 2017-2018')
                        ->required()
                        ->maxLength(20),
                    Select::make('department_id')
                        ->label('First Department')
                        ->options(Department::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Select::make('current_semester')
                        ->label('Starting Semester')
                        ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8))))
                        ->required()
                        ->default(1),
                ])
                ->action(function (array $data): void {
                    $exists = Batch::where('batch_number', $data['batch_number'])
                        ->where('department_id', $data['department_id'])
                        ->exists();

                    if ($exists) {
                        Notification::make()->warning()->title('This batch already exists for the selected department.')->send();

                        return;
                    }

                    Batch::create([
                        'batch_number' => $data['batch_number'],
                        'session' => $data['session'],
                        'department_id' => $data['department_id'],
                        'current_semester' => $data['current_semester'],
                        'is_active' => true,
                        'is_archived' => false,
                    ]);

                    $this->loadBatches();

                    Notification::make()->success()->title('Batch created.')->send();
                }),
        ];
    }
}
