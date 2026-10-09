<?php

namespace App\Services\People;

use App\Enums\RoleKey;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends PersonService<Student>
 */
class StudentService extends PersonService
{
    protected function modelClass(): string
    {
        return Student::class;
    }

    protected function resourceKey(): string
    {
        return 'student';
    }

    protected function defaultRole(): ?RoleKey
    {
        return RoleKey::Student;
    }

    protected function profileRules(?Model $record): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')],
            'batch_id' => ['required', 'integer', Rule::exists('batches', 'id')],
            'roll_number' => ['required', 'string', 'max:50', Rule::unique('students', 'roll_number')->ignore($record?->getKey())],
            'registration_number' => ['required', 'string', 'max:50', Rule::unique('students', 'registration_number')->ignore($record?->getKey())],
            'current_semester' => ['required', 'integer', 'between:1,8'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function filterableColumns(): array
    {
        return ['department_id', 'program_id', 'batch_id', 'current_semester'];
    }

    /**
     * Students the actor may list, searchable by name, email, roll or registration number.
     *
     * @return Builder<Student>
     */
    public function search(User $actor, string $term): Builder
    {
        return $this->query($actor)->where(function (Builder $query) use ($term): void {
            $query->where('roll_number', 'like', "%{$term}%")
                ->orWhere('registration_number', 'like', "%{$term}%")
                ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        });
    }
}
