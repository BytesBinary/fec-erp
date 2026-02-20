<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Staff\StaffResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function afterCreate(): void
    {
        $this->record->user->update(['role' => UserRole::Staff]);
    }
}
