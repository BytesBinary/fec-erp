<?php

namespace App\Filament\Resources\Students\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function handleRecordCreation(array $data): Student
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Student,
        ]);

        return Student::create([
            'user_id' => $user->id,
            'department_id' => $data['department_id'],
            'batch_id' => $data['batch_id'],
            'roll_number' => $data['roll_number'],
            'registration_number' => $data['registration_number'],
            'current_semester' => $data['current_semester'],
            'phone' => $data['phone'] ?? null,
        ]);
    }
}
