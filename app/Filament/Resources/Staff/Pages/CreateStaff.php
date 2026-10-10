<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Staff\StaffResource;
use App\Services\People\StaffService;
use Filament\Resources\Pages\CreateRecord;

class CreateStaff extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = StaffResource::class;

    protected static function domainService(): string
    {
        return StaffService::class;
    }
}
