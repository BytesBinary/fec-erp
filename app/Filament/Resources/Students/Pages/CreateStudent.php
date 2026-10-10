<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Students\StudentResource;
use App\Services\People\StudentService;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = StudentResource::class;

    protected static function domainService(): string
    {
        return StudentService::class;
    }
}
