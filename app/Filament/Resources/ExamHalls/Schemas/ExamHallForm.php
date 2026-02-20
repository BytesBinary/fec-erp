<?php

namespace App\Filament\Resources\ExamHalls\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExamHallForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
