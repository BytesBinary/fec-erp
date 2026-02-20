<?php

namespace App\Filament\Resources\Batches\Schemas;

use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Information')
                    ->columns(2)
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->options(Department::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('batch_number')
                            ->label('Batch Number')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->columnSpan(1),
                        TextInput::make('session')
                            ->label('Academic Session')
                            ->placeholder('e.g. 2017-2018')
                            ->required()
                            ->maxLength(20)
                            ->columnSpan(1),
                        Select::make('current_semester')
                            ->label('Current Semester')
                            ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8))))
                            ->required()
                            ->default(1)
                            ->columnSpan(1),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Deactivating will delete all routine slots for this batch.')
                            ->columnSpan(2),
                    ]),
            ]);
    }
}
