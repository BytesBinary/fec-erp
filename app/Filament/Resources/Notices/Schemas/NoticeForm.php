<?php

namespace App\Filament\Resources\Notices\Schemas;

use App\Models\Department;
use App\Models\Hall;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NoticeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Notice')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),
                        Select::make('audience')
                            ->options(['all' => 'Everyone', 'students' => 'Students', 'staff' => 'Staff'])
                            ->default('all')
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('Publish at')
                            ->default(now())
                            ->helperText('Leave empty to keep it as a draft.'),
                        Select::make('department_id')
                            ->label('Only for department')
                            ->options(Department::query()->where('is_active', true)->pluck('name', 'id'))
                            ->placeholder('All departments'),
                        Select::make('hall_id')
                            ->label('Only for hall')
                            ->options(Hall::query()->where('is_active', true)->pluck('name', 'id'))
                            ->placeholder('All halls'),
                    ]),
            ]);
    }
}
