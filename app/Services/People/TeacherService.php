<?php

namespace App\Services\People;

use App\Enums\RoleKey;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends PersonService<Teacher>
 */
class TeacherService extends PersonService
{
    protected function modelClass(): string
    {
        return Teacher::class;
    }

    protected function resourceKey(): string
    {
        return 'teacher';
    }

    protected function defaultRole(): ?RoleKey
    {
        return RoleKey::Teacher;
    }

    protected function profileRules(?Model $record): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'designation_id' => ['required', 'integer', Rule::exists('designations', 'id')],
            'employee_id' => ['required', 'string', 'max:50', Rule::unique('teachers', 'employee_id')->ignore($record?->getKey())],
            'short_name' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'joining_date' => ['nullable', 'date'],
        ];
    }

    protected function searchColumns(): array
    {
        return ['employee_id', 'short_name'];
    }

    protected function filterableColumns(): array
    {
        return ['department_id', 'designation_id'];
    }
}
