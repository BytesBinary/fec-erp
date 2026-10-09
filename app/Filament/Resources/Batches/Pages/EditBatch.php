<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Batches\BatchResource;
use App\Services\Academic\BatchService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBatch extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = BatchResource::class;

    protected static function domainService(): string
    {
        return BatchService::class;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
