<?php

namespace App\Services\Academic;

use App\Models\Department;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends CrudService<Department>
 */
class DepartmentService extends CrudService
{
    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function resourceKey(): string
    {
        return 'department';
    }

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($record?->getKey())],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    protected function filterableColumns(): array
    {
        return ['is_active'];
    }
}
