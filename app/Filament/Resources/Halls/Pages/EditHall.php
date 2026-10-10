<?php

namespace App\Filament\Resources\Halls\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Halls\HallResource;
use App\Services\Halls\HallService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditHall extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = HallResource::class;

    protected static function domainService(): string
    {
        return HallService::class;
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
