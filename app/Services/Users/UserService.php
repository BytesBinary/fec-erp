<?php

namespace App\Services\Users;

use App\Exceptions\Domain\InvalidStateException;
use App\Models\User;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Login accounts. Role assignment lives in {@see RoleService}.
 *
 * @extends CrudService<User>
 */
class UserService extends CrudService
{
    protected function modelClass(): string
    {
        return User::class;
    }

    protected function resourceKey(): string
    {
        return 'user';
    }

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($record?->getKey())],
            'password' => [$record === null ? 'required' : 'nullable', 'string', 'min:8'],
        ];
    }

    protected function searchColumns(): array
    {
        return ['name', 'email'];
    }

    protected function filterableColumns(): array
    {
        return ['is_active'];
    }

    protected function performUpdate(User $actor, Model $model, array $data): Model
    {
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return parent::performUpdate($actor, $model, $data);
    }

    public function deactivate(User $actor, User|int $user): User
    {
        return $this->setActive($actor, $user, false);
    }

    public function reactivate(User $actor, User|int $user): User
    {
        return $this->setActive($actor, $user, true);
    }

    /**
     * The acting user's own account (any active user).
     */
    public function me(User $actor): User
    {
        return $actor->load(['roles', 'roleScopes', 'student', 'teacher', 'staff']);
    }

    /**
     * Update the acting user's own name / email.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateOwnProfile(User $actor, array $data): User
    {
        $validated = $this->validate(array_intersect_key($data, array_flip(['name', 'email'])), $actor);

        $actor->update($validated);

        return $actor->refresh();
    }

    protected function setActive(User $actor, User|int $user, bool $active): User
    {
        $user = $this->resolve($user);

        $this->authorizer->authorize($actor, $this->permission('deactivate'), $user);

        if ($user->is($actor) && ! $active) {
            throw new InvalidStateException('You cannot deactivate your own account.');
        }

        $user->update(['is_active' => $active]);

        return $user->refresh();
    }
}
