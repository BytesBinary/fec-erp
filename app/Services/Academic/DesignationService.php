<?php

namespace App\Services\Academic;

use App\Enums\DesignationType;
use App\Models\Designation;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends CrudService<Designation>
 */
class DesignationService extends CrudService
{
    protected function modelClass(): string
    {
        return Designation::class;
    }

    protected function resourceKey(): string
    {
        return 'designation';
    }

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['required', 'string', 'max:30', Rule::unique('designations', 'short_name')->ignore($record?->getKey())],
            'type' => ['required', Rule::enum(DesignationType::class)],
            'is_active' => ['boolean'],
        ];
    }

    protected function searchColumns(): array
    {
        return ['name', 'short_name'];
    }
}
