<?php

namespace App\Filament\Resources\Notices\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Notices\NoticeResource;
use App\Services\Notices\NoticeService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditNotice extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = NoticeResource::class;

    protected static function domainService(): string
    {
        return NoticeService::class;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
