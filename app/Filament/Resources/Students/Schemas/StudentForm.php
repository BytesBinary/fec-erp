<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Models\Batch;
use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->description('User account for the student')
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
                Section::make('Academic Information')
                    ->columns(2)
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->options(Department::query()->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('batch_id', null))
                            ->columnSpan(1),
                        Select::make('batch_id')
                            ->label('Batch')
                            ->options(fn (callable $get) => Batch::query()
                                ->when($get('department_id'), fn ($q, $deptId) => $q->where('department_id', $deptId))
                                ->get()
                                ->mapWithKeys(fn (Batch $batch) => [$batch->id => "Batch {$batch->batch_number} ({$batch->session})"]))
                            ->searchable()
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('roll_number')
                            ->label('Roll Number')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),
                        TextInput::make('registration_number')
                            ->label('Registration Number')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),
                        Select::make('current_semester')
                            ->label('Current Semester')
                            ->options(array_combine(range(1, 8), array_map(fn ($n) => "Semester {$n}", range(1, 8))))
                            ->required()
                            ->default(1)
                            ->columnSpan(1),
                        TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(20)
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
