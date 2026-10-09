<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Services\Academic\DepartmentService;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartment extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = DepartmentResource::class;

    protected static function domainService(): string
    {
        return DepartmentService::class;
    }
}
