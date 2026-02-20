<?php

namespace App\Filament\Resources\Students\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('roll_number')
                    ->label('Roll No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('batch.batch_number')
                    ->label('Batch')
                    ->prefix('Batch ')
                    ->sortable(),
                TextColumn::make('current_semester')
                    ->label('Semester')
                    ->formatStateUsing(fn (int $state): string => "Sem {$state}")
                    ->sortable(),
                TextColumn::make('registration_number')
                    ->label('Registration No.')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('batch')
                    ->relationship('batch', 'batch_number')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "Batch {$record->batch_number} ({$record->session})")
                    ->searchable()
                    ->preload(),
                SelectFilter::make('current_semester')
                    ->label('Semester')
                    ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8)))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
