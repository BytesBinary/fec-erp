<?php

namespace App\Filament\Resources\ExamHalls\Pages;

use App\Filament\Resources\ExamHalls\ExamHallResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExamHall extends ViewRecord
{
    protected static string $resource = ExamHallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
