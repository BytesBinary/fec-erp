<?php

namespace App\Filament\Resources\Courses\Schemas;

use App\Enums\CourseType;
use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Course Information')
                    ->columns(2)
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->options(Department::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpan(1),
                        Select::make('semester_number')
                            ->label('Semester')
                            ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8))))
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('code')
                            ->label('Course Code')
                            ->required()
                            ->maxLength(30)
                            ->helperText('Automatically converted to uppercase.')
                            ->columnSpan(1),
                        Select::make('type')
                            ->label('Course Type')
                            ->options(CourseType::class)
                            ->required()
                            ->default(CourseType::Theory->value)
                            ->columnSpan(1),
                        TextInput::make('name')
                            ->label('Course Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('credit_hours')
                            ->label('Credit Hours')
                            ->numeric()
                            ->required()
                            ->default(3.0)
                            ->step(0.5)
                            ->minValue(0.5)
                            ->maxValue(6.0)
                            ->columnSpan(1),
                        TextInput::make('weekly_classes')
                            ->label('Classes per Week')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(7)
                            ->placeholder('Default: 1')
                            ->helperText('Leave blank to use default (1 class/week).')
                            ->columnSpan(1),
                        Toggle::make('is_active')
                            ->default(true)
                            ->columnSpan(1),
                    ]),
                Section::make('Versioning')
                    ->description('Only set a version number if this course is a revised variant of an existing course with the same code.')
                    ->collapsed()
                    ->schema([
                        Select::make('version')
                            ->label('Version')
                            ->options([
                                1 => 'Version 1',
                                2 => 'Version 2',
                                3 => 'Version 3',
                            ])
                            ->placeholder('— No version (standard course) —')
                            ->helperText('Use only when the same course code has old and new syllabus versions running in parallel.'),
                        Textarea::make('description')
                            ->maxLength(1000),
                    ]),
            ]);
    }
}
