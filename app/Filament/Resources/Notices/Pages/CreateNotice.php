<?php

namespace App\Filament\Resources\Notices\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Notices\NoticeResource;
use App\Services\Notices\NoticeService;
use Filament\Resources\Pages\CreateRecord;

class CreateNotice extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = NoticeResource::class;

    protected static function domainService(): string
    {
        return NoticeService::class;
    }
}
