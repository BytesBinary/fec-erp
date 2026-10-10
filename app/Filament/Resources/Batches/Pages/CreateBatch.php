<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Batches\BatchResource;
use App\Services\Academic\BatchService;
use Filament\Resources\Pages\CreateRecord;

class CreateBatch extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = BatchResource::class;

    protected static function domainService(): string
    {
        return BatchService::class;
    }
}
