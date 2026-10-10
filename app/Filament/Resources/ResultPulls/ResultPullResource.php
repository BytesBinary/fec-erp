<?php

namespace App\Filament\Resources\ResultPulls;

use App\Enums\PortalChangeType;
use App\Enums\ResultPullStatus;
use App\Filament\Resources\ResultPulls\Pages\ListResultPulls;
use App\Filament\Resources\ResultPulls\Pages\ViewResultPull;
use App\Models\PortalResult;
use App\Models\ResultPull;
use App\Services\ResultPortal\ResultPullService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * "Student results": which students' official results were pulled from the
 * exam portal, which failed and why, with a retry. Scoped like students (a
 * department head sees only their department).
 */
class ResultPullResource extends Resource
{
    protected static ?string $model = ResultPull::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Academic';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'student-results';

    public static function getNavigationLabel(): string
    {
        return __('erp.result_pull.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('erp.result_pull.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.result_pull.plural_model_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return app(ResultPullService::class)->query(Auth::user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('student.user.name')->label('Student')->searchable(),
                TextColumn::make('student.roll_number')->label('Roll')->searchable(),
                TextColumn::make('student.registration_number')->label('Registration no.')->searchable(),
                TextColumn::make('student.department.code')->label('Dept.'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ResultPullStatus $state): string => $state->color())
                    ->formatStateUsing(fn (ResultPullStatus $state): string => $state->label()),
                TextColumn::make('results_found')->label('Results')->numeric(),
                TextColumn::make('results_changed')->label('New / changed')->numeric(),
                TextColumn::make('message')->label('Details')->wrap()->limit(90)->placeholder('—'),
                TextColumn::make('trigger')
                    ->formatStateUsing(fn (string $state): string => __("erp.result_pull.triggers.{$state}"))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('finished_at')->label('Finished')->since()->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ResultPullStatus::cases())->mapWithKeys(fn (ResultPullStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('department')
                    ->label('Department')
                    ->relationship('student.department', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (ResultPull $record): bool => in_array($record->status, [ResultPullStatus::Failed, ResultPullStatus::Skipped], true))
                    ->action(function (ResultPull $record): void {
                        app(ResultPullService::class)->retry(Auth::user(), $record);
                        Notification::make()->title('Pull queued again')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('retrySelected')
                    ->label('Retry selected')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (Collection $records): void {
                        $records->each(fn (ResultPull $record) => app(ResultPullService::class)->retry(Auth::user(), $record));
                        Notification::make()->title('Pulls queued again')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pull')->columns(3)->schema([
                TextEntry::make('student.user.name')->label('Student'),
                TextEntry::make('student.registration_number')->label('Registration no.'),
                TextEntry::make('status')->badge()
                    ->color(fn (ResultPullStatus $state): string => $state->color())
                    ->formatStateUsing(fn (ResultPullStatus $state): string => $state->label()),
                TextEntry::make('message')->label('Details')->columnSpanFull()->placeholder('—'),
                TextEntry::make('attempts'),
                TextEntry::make('exams_checked')->label('Exams checked'),
                TextEntry::make('finished_at')->dateTime()->placeholder('—'),
            ]),
            Section::make('Pulled results')->schema([
                RepeatableEntry::make('student.portalResults')
                    ->hiddenLabel()
                    ->contained(false)
                    ->schema([
                        TextEntry::make('course_code')->label('Course'),
                        TextEntry::make('letter')->label('Grade'),
                        TextEntry::make('grade_point')->label('Point'),
                        TextEntry::make('change_type')->label('Change')->badge()->placeholder('—')
                            ->color(fn (?PortalChangeType $state): string => $state?->color() ?? 'gray')
                            ->formatStateUsing(fn (?PortalChangeType $state, PortalResult $record): string => $state === null ? '—' : "{$state->label()} ({$record->previous_letter} → {$record->letter})"),
                        TextEntry::make('is_current')->label('Counted')->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'Replaced'),
                        TextEntry::make('exam_title')->label('Exam')->columnSpan(2),
                    ])
                    ->columns(7),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResultPulls::route('/'),
            'view' => ViewResultPull::route('/{record}'),
        ];
    }
}
