<?php

namespace App\Filament\Resources\ExamDuties;

use App\Filament\Resources\ExamDuties\Pages\CreateExamDuty;
use App\Filament\Resources\ExamDuties\Pages\ListExamDuties;
use App\Filament\Resources\ExamDuties\Pages\ViewExamDuty;
use App\Filament\Resources\ExamDuties\Tables\ExamDutiesTable;
use App\Models\ExamDuty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ExamDutyResource extends Resource
{
    protected static ?string $model = ExamDuty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Megaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Mange Exams';

    protected static ?string $recordTitleAttribute = 'ExamDuty';

    public static function table(Table $table): Table
    {
        return ExamDutiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExamDuties::route('/'),
            'create' => CreateExamDuty::route('/create'),
            'view' => ViewExamDuty::route('/{record}'),
            'edit' => CreateExamDuty::route('/{record}/edit'),
        ];
    }
}
