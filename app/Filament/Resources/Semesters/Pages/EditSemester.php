<?php

namespace App\Filament\Resources\Semesters\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Semesters\SemesterResource;
use App\Services\Academic\SemesterService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSemester extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = SemesterResource::class;

    protected static function domainService(): string
    {
        return SemesterService::class;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
