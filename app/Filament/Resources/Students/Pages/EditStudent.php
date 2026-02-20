<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $userUpdates = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (filled($data['password'] ?? null)) {
            $userUpdates['password'] = $data['password'];
        }

        $this->record->user->update($userUpdates);

        unset($data['name'], $data['email'], $data['password']);

        return $data;
    }
}
