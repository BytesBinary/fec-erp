<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Programs\ProgramResource;
use App\Services\Academic\ProgramService;
use Filament\Resources\Pages\CreateRecord;

class CreateProgram extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = ProgramResource::class;

    protected static function domainService(): string
    {
        return ProgramService::class;
    }
}
