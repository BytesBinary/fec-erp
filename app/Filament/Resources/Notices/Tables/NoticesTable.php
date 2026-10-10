<?php

namespace App\Filament\Resources\Notices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class NoticesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('audience')
                    ->badge(),
                TextColumn::make('department.code')
                    ->label('Department')
                    ->placeholder('All'),
                TextColumn::make('hall.code')
                    ->label('Hall')
                    ->placeholder('All'),
                TextColumn::make('author.name')
                    ->label('By'),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->placeholder('Draft')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('audience')
                    ->options(['all' => 'Everyone', 'students' => 'Students', 'staff' => 'Staff']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('published_at', 'desc');
    }
}
