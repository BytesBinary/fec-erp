<?php

namespace App\Filament\Resources\Designations\Tables;

use App\Enums\DesignationType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DesignationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('short_name')
                    ->label('Short Name')
                    ->badge()
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (DesignationType $state): string => $state->color())
                    ->formatStateUsing(fn (DesignationType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('teachers_count')
                    ->label('Teachers')
                    ->counts('teachers')
                    ->sortable(),
                TextColumn::make('staff_count')
                    ->label('Staff')
                    ->counts('staff')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(DesignationType::class),
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
            ]);
    }
}
