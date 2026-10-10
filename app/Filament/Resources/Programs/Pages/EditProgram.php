<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Programs\ProgramResource;
use App\Services\Academic\ProgramService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProgram extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = ProgramResource::class;

    protected static function domainService(): string
    {
        return ProgramService::class;
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
