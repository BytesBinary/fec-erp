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

class BatchDetail extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Academic';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'batch-detail/{batchNumber}';

    protected string $view = 'filament.pages.batch-detail';

    public int $batchNumber;

    /** @var array<int, array{id: int, department_name: string, session: string, current_semester: int, is_active: bool, is_archived: bool}> */
    public array $rows = [];

    public function mount(int $batchNumber): void
    {
        $this->batchNumber = $batchNumber;
        $this->loadRows();
    }

    public function getTitle(): string
    {
        return isset($this->batchNumber) ? "Batch {$this->batchNumber}" : 'Batch Detail';
    }

    private function loadRows(): void
    {
        $this->rows = Batch::where('batch_number', $this->batchNumber)
            ->with('department')
            ->orderBy('department_id')
            ->get()
            ->map(fn (Batch $b) => [
                'id' => $b->id,
                'department_name' => $b->department->name,
                'session' => $b->session,
                'current_semester' => $b->current_semester,
                'is_active' => $b->is_active,
                'is_archived' => $b->is_archived,
            ])
            ->toArray();
    }

    public function updateSemester(int $batchId, int $semester): void
    {
        Batch::find($batchId)?->update(['current_semester' => $semester]);
        $this->loadRows();

        Notification::make()->success()->title('Semester updated.')->send();
    }

    public function toggleActive(int $batchId): void
    {
        $batch = Batch::find($batchId);

        if (! $batch) {
            return;
        }

        $batch->update(['is_active' => ! $batch->is_active]);
        $this->loadRows();

        Notification::make()->success()->title($batch->is_active ? 'Batch deactivated.' : 'Batch activated.')->send();
    }

    public function archiveBatchAction(): Action
    {
        return Action::make('archiveBatch')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Archive this department batch?')
            ->modalDescription('The routine history is preserved. This batch will be hidden from routine and batch views.')
            ->action(function (array $arguments): void {
                Batch::find($arguments['batchId'])?->update(['is_archived' => true, 'is_active' => false]);
                $this->loadRows();

                Notification::make()->success()->title('Batch archived.')->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Batches')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(BatchOverview::getUrl()),

            Action::make('addDepartment')
                ->label('Add Department')
                ->icon(Heroicon::OutlinedPlus)
                ->color('primary')
                ->modalHeading("Add Department to Batch {$this->batchNumber}")
                ->form([
                    Select::make('department_id')
                        ->label('Department')
                        ->options(function (): array {
                            $existingDeptIds = Batch::where('batch_number', $this->batchNumber)
                                ->pluck('department_id')
                                ->toArray();

                            return Department::where('is_active', true)
                                ->whereNotIn('id', $existingDeptIds)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->required(),
                    TextInput::make('session')
                        ->label('Academic Session')
                        ->placeholder('e.g. 2017-2018')
                        ->required()
                        ->maxLength(20),
                    Select::make('current_semester')
                        ->label('Current Semester')
                        ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8))))
                        ->required()
                        ->default(1),
                ])
                ->action(function (array $data): void {
                    Batch::create([
                        'batch_number' => $this->batchNumber,
                        'department_id' => $data['department_id'],
                        'session' => $data['session'],
                        'current_semester' => $data['current_semester'],
                        'is_active' => true,
                        'is_archived' => false,
                    ]);

                    $this->loadRows();

                    Notification::make()->success()->title('Department added to batch.')->send();
                }),
        ];
    }
}
