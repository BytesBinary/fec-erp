<?php

namespace App\Filament\Resources\ExamHalls;

use App\Filament\Resources\ExamHalls\Pages\CreateExamHall;
use App\Filament\Resources\ExamHalls\Pages\EditExamHall;
use App\Filament\Resources\ExamHalls\Pages\ListExamHalls;
use App\Filament\Resources\ExamHalls\Pages\ViewExamHall;
use App\Filament\Resources\ExamHalls\Schemas\ExamHallForm;
use App\Filament\Resources\ExamHalls\Schemas\ExamHallInfolist;
use App\Filament\Resources\ExamHalls\Tables\ExamHallsTable;
use App\Models\ExamHall;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ExamHallResource extends Resource
{
    protected static ?string $model = ExamHall::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Manage Exams';

    protected static ?string $recordTitleAttribute = 'ExamHall';

    public static function form(Schema $schema): Schema
    {
        return ExamHallForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExamHallInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamHallsTable::configure($table);
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
            'index' => ListExamHalls::route('/'),
            'create' => CreateExamHall::route('/create'),
            'view' => ViewExamHall::route('/{record}'),
            'edit' => EditExamHall::route('/{record}/edit'),
        ];
    }
}
