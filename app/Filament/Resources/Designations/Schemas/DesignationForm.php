<?php

namespace App\Filament\Resources\Designations\Schemas;

use App\Enums\DesignationType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DesignationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Designation Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->columnSpan(1),
                        TextInput::make('short_name')
                            ->label('Short Name')
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true)
                            ->helperText('Used as abbreviation, e.g. "Prof.", "Asst. Prof."')
                            ->columnSpan(1),
                        Select::make('type')
                            ->label('Applies To')
                            ->options(DesignationType::class)
                            ->required()
                            ->columnSpan(1),
                        Toggle::make('is_active')
                            ->default(true)
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
