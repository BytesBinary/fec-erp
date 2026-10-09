<?php

namespace App\Services\Academic;

use App\Models\Program;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends CrudService<Program>
 */
class ProgramService extends CrudService
{
    protected function modelClass(): string
    {
        return Program::class;
    }

    protected function resourceKey(): string
    {
        return 'program';
    }

    protected function rules(?Model $record): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('programs', 'code')->ignore($record?->getKey())],
            'required_credits' => ['required', 'numeric', 'min:0', 'max:999'],
            'total_semesters' => ['integer', 'min:1', 'max:16'],
            'is_active' => ['boolean'],
        ];
    }

    protected function scopeColumns(): array
    {
        return ['department' => 'department_id'];
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }

    protected function filterableColumns(): array
    {
        return ['department_id', 'is_active'];
    }
}
