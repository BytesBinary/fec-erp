<?php

namespace App\Services\People;

use App\Enums\RoleKey;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends PersonService<Staff>
 */
class StaffService extends PersonService
{
    protected function modelClass(): string
    {
        return Staff::class;
    }

    protected function resourceKey(): string
    {
        return 'staff';
    }

    protected function defaultRole(): ?RoleKey
    {
        return null;
    }

    protected function profileRules(?Model $record): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'designation_id' => ['required', 'integer', Rule::exists('designations', 'id')],
            'employee_id' => ['required', 'string', 'max:50', Rule::unique('staff', 'employee_id')->ignore($record?->getKey())],
            'phone' => ['nullable', 'string', 'max:20'],
            'joining_date' => ['nullable', 'date'],
        ];
    }

    protected function searchColumns(): array
    {
        return ['employee_id'];
    }

    protected function filterableColumns(): array
    {
        return ['department_id', 'designation_id'];
    }
}
