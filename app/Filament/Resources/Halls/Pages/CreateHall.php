<?php

namespace App\Filament\Resources\Halls\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Halls\HallResource;
use App\Services\Halls\HallService;
use Filament\Resources\Pages\CreateRecord;

class CreateHall extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = HallResource::class;

    protected static function domainService(): string
    {
        return HallService::class;
    }
}
