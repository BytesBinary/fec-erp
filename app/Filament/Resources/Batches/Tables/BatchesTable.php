<?php

namespace App\Filament\Resources\Batches\Tables;

use App\Models\Batch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->sortable()
                    ->prefix('Batch '),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('session')
                    ->label('Session')
                    ->sortable(),
                TextColumn::make('current_semester')
                    ->label('Current Semester')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => "Semester {$state}"),
                TextColumn::make('students_count')
                    ->label('Students')
                    ->counts('students')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                IconColumn::make('is_archived')
                    ->label('Archived')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedArchiveBox)
                    ->falseIcon(Heroicon::OutlinedArchiveBoxXMark)
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('current_semester')
                    ->label('Semester')
                    ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8)))),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('is_archived')
                    ->label('Archived')
                    ->default(false),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('archive')
                    ->label('Archive')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Archive Batch')
                    ->modalDescription('Archiving this batch will hide it from the routine and batch views. Its routine history is preserved. Continue?')
                    ->modalSubmitActionLabel('Yes, Archive')
                    ->visible(fn (Batch $record): bool => ! $record->is_archived)
                    ->action(function (Batch $record): void {
                        $record->update(['is_archived' => true, 'is_active' => false]);

                        Notification::make()
                            ->title('Batch archived successfully.')
                            ->success()
                            ->send();
                    }),
                Action::make('unarchive')
                    ->label('Unarchive')
                    ->icon(Heroicon::OutlinedArchiveBoxXMark)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Unarchive Batch')
                    ->modalSubmitActionLabel('Yes, Unarchive')
                    ->visible(fn (Batch $record): bool => $record->is_archived)
                    ->action(function (Batch $record): void {
                        $record->update(['is_archived' => false]);

                        Notification::make()
                            ->title('Batch unarchived.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('batch_number');
    }
}
