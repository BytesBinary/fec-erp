<?php

namespace App\Services\Academic;

use App\Models\Batch;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends CrudService<Batch>
 */
class BatchService extends CrudService
{
    protected function modelClass(): string
    {
        return Batch::class;
    }

    protected function resourceKey(): string
    {
        return 'batch';
    }

    protected function rules(?Model $record): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'batch_number' => ['required', 'integer', 'min:1'],
            'session' => ['required', 'string', 'max:20'],
            'current_semester' => ['required', 'integer', 'between:1,8'],
            'is_active' => ['boolean'],
        ];
    }

    protected function scopeColumns(): array
    {
        return ['department' => 'department_id'];
    }

    protected function searchColumns(): array
    {
        return ['session'];
    }

    protected function filterableColumns(): array
    {
        return ['department_id', 'is_active'];
    }
}
