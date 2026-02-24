<?php

namespace App\Filament\Resources\ExamTypes;

use App\Filament\Resources\ExamTypes\Pages\CreateExamType;
use App\Filament\Resources\ExamTypes\Pages\EditExamType;
use App\Filament\Resources\ExamTypes\Pages\ListExamTypes;
use App\Filament\Resources\ExamTypes\Pages\ViewExamType;
use App\Filament\Resources\ExamTypes\Schemas\ExamTypeForm;
use App\Filament\Resources\ExamTypes\Schemas\ExamTypeInfolist;
use App\Filament\Resources\ExamTypes\Tables\ExamTypesTable;
use App\Models\ExamType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ExamTypeResource extends Resource
{
    protected static ?string $model = ExamType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Funnel;

    protected static string|UnitEnum|null $navigationGroup = 'Manage Exams';

    protected static ?string $recordTitleAttribute = 'ExamType';

    public static function form(Schema $schema): Schema
    {
        return ExamTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExamTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamTypesTable::configure($table);
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
            'index' => ListExamTypes::route('/'),
            'create' => CreateExamType::route('/create'),
            'view' => ViewExamType::route('/{record}'),
            'edit' => EditExamType::route('/{record}/edit'),
        ];
    }
}
