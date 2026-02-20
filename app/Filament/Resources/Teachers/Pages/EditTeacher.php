<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTeacher extends EditRecord
{
    protected static string $resource = TeacherResource::class;

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
