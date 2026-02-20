<?php

namespace App\Filament\Resources\ExamHalls\Pages;

use App\Filament\Resources\ExamHalls\ExamHallResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExamHall extends EditRecord
{
    protected static string $resource = ExamHallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
