<?php

namespace App\Services\People;

use App\Enums\RoleKey;
use App\Models\User;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Students, teachers and staff are a profile row plus a login account. This
 * base creates/updates both together; `name`, `email` and `password` go to
 * the user, everything else to the profile.
 *
 * @template TModel of Model
 *
 * @extends CrudService<TModel>
 */
abstract class PersonService extends CrudService
{
    /**
     * Role given to the account on creation (null = none).
     */
    abstract protected function defaultRole(): ?RoleKey;

    /**
     * Profile-specific rules.
     *
     * @return array<string, mixed>
     */
    abstract protected function profileRules(?Model $record): array;

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($record?->user_id)],
            'password' => [$record === null ? 'required' : 'nullable', 'string', 'min:8'],
            ...$this->profileRules($record),
        ];
    }

    protected function scopeColumns(): array
    {
        return ['department' => 'department_id', 'self' => 'user_id'];
    }

    protected function performCreate(User $actor, array $data): Model
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        if ($this->defaultRole() !== null) {
            $user->assignRole(Role::findOrCreate($this->defaultRole()->value, 'web'));
        }

        $profile = $this->modelClass()::query()->create([
            ...$this->profileData($data),
            'user_id' => $user->id,
        ]);

        return $profile->load('user');
    }

    protected function performUpdate(User $actor, Model $model, array $data): Model
    {
        $userUpdates = array_intersect_key($data, array_flip(['name', 'email']));

        if (filled($data['password'] ?? null)) {
            $userUpdates['password'] = $data['password'];
        }

        if ($userUpdates !== []) {
            $model->user->update($userUpdates);
        }

        $model->update($this->profileData($data));

        return $model->refresh()->load('user');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function profileData(array $data): array
    {
        return array_diff_key($data, array_flip(['name', 'email', 'password', 'user_id']));
    }

    protected function searchColumns(): array
    {
        return [];
    }
}
