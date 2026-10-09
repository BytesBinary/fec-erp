<?php

namespace App\Filament\Resources\Programs\Schemas;

use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Program')
                    ->columns(2)
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->options(Department::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        TextInput::make('code')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->placeholder('BSC-CSE'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('B.Sc. in Computer Science and Engineering')
                            ->columnSpanFull(),
                        TextInput::make('required_credits')
                            ->label('Credits required to graduate')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(160),
                        TextInput::make('total_semesters')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(16)
                            ->default(8),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }
}
