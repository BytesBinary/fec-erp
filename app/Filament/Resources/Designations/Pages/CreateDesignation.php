<?php

namespace App\Filament\Resources\Designations\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Designations\DesignationResource;
use App\Services\Academic\DesignationService;
use Filament\Resources\Pages\CreateRecord;

class CreateDesignation extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = DesignationResource::class;

    protected static function domainService(): string
    {
        return DesignationService::class;
    }
}
