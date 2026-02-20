<?php

namespace App\Filament\Resources\Courses\Tables;

use App\Enums\CourseType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (CourseType $state): string => $state->color())
                    ->formatStateUsing(fn (CourseType $state): string => $state->label()),
                TextColumn::make('version')
                    ->label('Ver.')
                    ->formatStateUsing(fn (?int $state): string => $state ? "v{$state}" : '—')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Course Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('semester_number')
                    ->label('Semester')
                    ->formatStateUsing(fn (int $state): string => "Semester {$state}")
                    ->sortable(),
                TextColumn::make('credit_hours')
                    ->label('Credits')
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
                SelectFilter::make('semester_number')
                    ->label('Semester')
                    ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8)))),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('semester_number');
    }
}
