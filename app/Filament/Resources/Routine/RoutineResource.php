<?php

namespace App\Filament\Resources\Routine;

use App\Filament\Resources\Routine\Pages\ManageRoutine;
use App\Filament\Resources\Routine\Pages\RoutineIndex;
use App\Models\Batch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class RoutineResource extends Resource
{
    protected static ?string $model = Batch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Routine';

    protected static ?string $navigationLabel = 'Routine';

    protected static ?string $modelLabel = 'Routine';

    protected static ?string $pluralModelLabel = 'Routines';

    protected static ?string $slug = 'routine';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.code')
                    ->label('Dept')
                    ->badge()
                    ->sortable(),
                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->prefix('Batch ')
                    ->sortable(),
                TextColumn::make('session')
                    ->label('Session')
                    ->sortable(),
                TextColumn::make('current_semester')
                    ->label('Semester')
                    ->formatStateUsing(fn (int $state): string => "Semester {$state}")
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])
            ->recordActions([
                Action::make('manage')
                    ->label('Manage Routine')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Batch $record): string => ManageRoutine::getUrl(['record' => $record]))
                    ->disabled(fn (Batch $record): bool => ! $record->is_active),
            ])
            ->defaultSort('batch_number');
    }

    public static function getPages(): array
    {
        return [
            'index' => RoutineIndex::route('/'),
            'manage' => ManageRoutine::route('/{record}/manage'),
        ];
    }
}
