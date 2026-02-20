<?php

namespace App\Filament\Resources\Teachers\Schemas;

use App\Enums\DesignationType;
use App\Models\Department;
use App\Models\Designation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TeacherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->description('User account for the teacher')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'users', ignorable: fn ($record) => $record?->user)
                            ->columnSpan(1),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->columnSpan(1),
                    ]),
                Section::make('Teacher Profile')
                    ->columns(2)
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->options(Department::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpan(1),
                        Select::make('designation_id')
                            ->label('Designation')
                            ->options(
                                Designation::query()
                                    ->where('is_active', true)
                                    ->where('type', DesignationType::Teacher->value)
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('employee_id')
                            ->label('Employee ID')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),
                        TextInput::make('short_name')
                            ->label('Short Name / Initials')
                            ->maxLength(50)
                            ->helperText('Used for routine generation, e.g. MRA, KH')
                            ->columnSpan(1),
                        TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(20)
                            ->columnSpan(1),
                        DatePicker::make('joining_date')
                            ->label('Joining Date')
                            ->maxDate(now())
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
