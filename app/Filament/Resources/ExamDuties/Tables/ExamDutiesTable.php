<?php

namespace App\Filament\Resources\ExamDuties\Tables;

use App\Models\ExamType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ExamDutiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('exam_name')
                    ->label('Exam Name'),
                TextColumn::make('exam_type_id')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(function (string $state) {
                        $states = str_replace(['[', ']', '"'], '', $state);
                        $states = explode(',', $states);
                        $examType = '';
                        foreach ($states as $state) {
                            $examType .= ExamType::find($state)->type.(end($states) === $state ? '' : ',');
                        }

                        return $examType;
                    })
                    ->label('Exam Type'),
                TextColumn::make('semester')
                    ->badge('secondary')
                    ->formatStateUsing(function (string $state) {
                        $state = str_replace(['[', ']', '"'], '', $state);

                        return $state;
                    })
                    ->label('Semester'),
                TextColumn::make('exam_year')
                    ->badge('primary')
                    ->label('Exam Year'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
            ]);
    }
}
