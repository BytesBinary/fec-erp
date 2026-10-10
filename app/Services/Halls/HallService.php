<?php

namespace App\Services\Halls;

use App\Models\Hall;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Residential halls. Residencies and dues are added with the clearance module.
 *
 * @extends CrudService<Hall>
 */
class HallService extends CrudService
{
    protected function modelClass(): string
    {
        return Hall::class;
    }

    protected function resourceKey(): string
    {
        return 'hall';
    }

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('halls', 'code')->ignore($record?->getKey())],
            'gender' => ['nullable', Rule::in(['male', 'female', 'mixed'])],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function scopeColumns(): array
    {
        return ['hall' => 'id'];
    }

    protected function searchColumns(): array
    {
        return ['name', 'code'];
    }
}
