<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Courses\CourseResource;
use App\Services\Academic\CourseService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCourse extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = CourseResource::class;

    protected static function domainService(): string
    {
        return CourseService::class;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
