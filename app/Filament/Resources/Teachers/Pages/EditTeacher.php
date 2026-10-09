<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Services\People\TeacherService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTeacher extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = TeacherResource::class;

    protected static function domainService(): string
    {
        return TeacherService::class;
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
