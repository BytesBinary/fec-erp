<?php

namespace App\Filament\Resources\ExamDuties\Pages;

use App\Filament\Resources\ExamDuties\ExamDutyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExamDuties extends ListRecords
{
    protected static string $resource = ExamDutyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
