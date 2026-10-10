<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Services\People\TeacherService;
use Filament\Resources\Pages\CreateRecord;

class CreateTeacher extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = TeacherResource::class;

    protected static function domainService(): string
    {
        return TeacherService::class;
    }
}
