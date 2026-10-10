<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Staff\StaffResource;
use App\Services\People\StaffService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = StaffResource::class;

    protected static function domainService(): string
    {
        return StaffService::class;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record->user;
        $data['name'] = $user?->name;
        $data['email'] = $user?->email;

        return $data;
    }
}
