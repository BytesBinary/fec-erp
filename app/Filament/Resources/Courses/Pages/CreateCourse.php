<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Courses\CourseResource;
use App\Services\Academic\CourseService;
use Filament\Resources\Pages\CreateRecord;

class CreateCourse extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = CourseResource::class;

    protected static function domainService(): string
    {
        return CourseService::class;
    }
}
