<?php

namespace App\Filament\Resources\Semesters\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Semesters\SemesterResource;
use App\Services\Academic\SemesterService;
use Filament\Resources\Pages\CreateRecord;

class CreateSemester extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = SemesterResource::class;

    protected static function domainService(): string
    {
        return SemesterService::class;
    }
}
