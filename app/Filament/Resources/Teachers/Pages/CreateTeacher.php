<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\Teacher;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    protected function handleRecordCreation(array $data): Teacher
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Teacher,
        ]);

        return Teacher::create([
            'user_id' => $user->id,
            'department_id' => $data['department_id'],
            'designation_id' => $data['designation_id'],
            'employee_id' => $data['employee_id'],
            'short_name' => $data['short_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'joining_date' => $data['joining_date'] ?? null,
        ]);
    }
}
