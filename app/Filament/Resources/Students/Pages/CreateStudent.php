<?php

namespace App\Filament\Resources\Students\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Students\StudentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function afterCreate(): void
    {
        $this->record->user->update(['role' => UserRole::Student]);
    }
}
