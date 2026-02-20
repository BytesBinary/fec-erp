<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    protected function afterCreate(): void
    {
        $this->record->user->update(['role' => UserRole::Teacher]);
    }
}
