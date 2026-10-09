<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Services\Academic\DepartmentService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDepartment extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = DepartmentResource::class;

    protected static function domainService(): string
    {
        return DepartmentService::class;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
