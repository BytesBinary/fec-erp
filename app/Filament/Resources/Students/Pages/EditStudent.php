<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Students\StudentResource;
use App\Services\People\StudentService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = StudentResource::class;

    protected static function domainService(): string
    {
        return StudentService::class;
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
        $data['name'] = $user->name;
        $data['email'] = $user->email;

        return $data;
    }
}
