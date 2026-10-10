<?php

namespace App\Mcp\Domains;

use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\User;
use App\Services\Users\PermissionMatrixService;
use App\Services\Users\RoleService;
use App\Services\Users\UserService;

/**
 * Users, roles and the permission matrix (super admin).
 */
final class UserTools
{
    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        $userPreview = fn (User $actor, array $args): array => ['user' => UserService::class, 'id' => $args['id'], 'effect' => 'The account is switched off or on; sessions of a deactivated user are rejected immediately.'];

        return [
            ...ToolSupport::crud('user', 'user', UserService::class, 'user', hint: 'Roles are assigned with role_assign.'),

            ToolDefinition::make('user_deactivate', 'Deactivate user', 'Deactivate a login account. The user is signed out everywhere and cannot sign in. Reversible with user_reactivate. Needs confirm=true.')
                ->permission('user:deactivate')
                ->params(Param::integer('id', 'The user id.', required: true))
                ->destructive(function (User $actor, array $args): array {
                    $user = User::query()->findOrFail($args['id']);

                    return ['user' => $user->only(['id', 'name', 'email']), 'effect' => 'This account will be deactivated and signed out everywhere.'];
                })
                ->covers(UserService::class.'::deactivate')
                ->handler(function (User $actor, array $args): array {
                    $user = app(UserService::class)->deactivate($actor, (int) $args['id']);

                    return ['summary' => "Deactivated {$user->name}.", 'data' => $user->only(['id', 'name', 'email', 'is_active']), 'entity' => ['User', $user->id]];
                }),

            ToolDefinition::make('user_reactivate', 'Reactivate user', 'Reactivate a previously deactivated login account so the user can sign in again. Use after user_deactivate; returns the updated user.')
                ->permission('user:deactivate')
                ->params(Param::integer('id', 'The user id.', required: true))
                ->write(idempotent: true)
                ->covers(UserService::class.'::reactivate')
                ->handler(function (User $actor, array $args): array {
                    $user = app(UserService::class)->reactivate($actor, (int) $args['id']);

                    return ['summary' => "Reactivated {$user->name}.", 'data' => $user->only(['id', 'name', 'email', 'is_active']), 'entity' => ['User', $user->id]];
                }),

            ToolDefinition::make('role_list', 'List roles', 'List all roles with how many users hold each. Use it to learn valid role keys before role_assign or role_revoke.')
                ->permission('role:list')
                ->covers(RoleService::class.'::list')
                ->handler(function (User $actor): array {
                    $roles = app(RoleService::class)->list($actor)->map(fn ($role): array => ['name' => $role->name, 'users' => $role->users_count])->all();

                    return ['summary' => count($roles).' role(s).', 'data' => $roles];
                }),

            ToolDefinition::make('role_assign', 'Assign role', 'Give a user a role. department_head needs department_ids, hall_provost needs hall_ids, teacher may take extra course ids. Roles are additive: a user may hold several.')
                ->permission('role:assign')
                ->params(Param::integer('user_id', 'The user id.', required: true), Param::string('role', 'Role key, e.g. department_head.', required: true), Param::array('scope_ids', 'Department, hall or course ids the role is limited to.', 'integer'))
                ->write(idempotent: true)
                ->covers(RoleService::class.'::assign')
                ->handler(function (User $actor, array $args): array {
                    $user = app(RoleService::class)->assign($actor, User::query()->findOrFail($args['user_id']), $args['role'], array_map('intval', $args['scope_ids'] ?? []));

                    return ['summary' => "Assigned {$args['role']} to {$user->name}.", 'data' => app(RoleService::class)->rolesOf($actor, $user), 'entity' => ['User', $user->id]];
                }),

            ToolDefinition::make('role_revoke', 'Revoke role', 'Remove a role from a user; they lose its permissions at once. Needs confirm=true (without it you get a preview). Use role_list first for the role key.')
                ->permission('role:revoke')
                ->params(Param::integer('user_id', 'The user id.', required: true), Param::string('role', 'Role key.', required: true))
                ->destructive(fn (User $actor, array $args): array => ['user_id' => $args['user_id'], 'role' => $args['role'], 'effect' => 'The user loses this role and its permissions.'])
                ->covers(RoleService::class.'::revoke')
                ->handler(function (User $actor, array $args): array {
                    $user = app(RoleService::class)->revoke($actor, User::query()->findOrFail($args['user_id']), $args['role']);

                    return ['summary' => "Revoked {$args['role']} from {$user->name}.", 'data' => app(RoleService::class)->rolesOf($actor, $user), 'entity' => ['User', $user->id]];
                }),

            ToolDefinition::make('permission_matrix_get', 'Permission matrix', 'Get which permissions each role holds (role name to permission names).')
                ->permission('permission_matrix:view')
                ->covers(PermissionMatrixService::class.'::get')
                ->handler(fn (User $actor): array => ['summary' => 'Permission matrix.', 'data' => app(PermissionMatrixService::class)->get($actor)]),

            ToolDefinition::make('permission_matrix_update', 'Update permission matrix', 'Grant and/or revoke permissions of one role. Needs confirm=true. Changing what roles may do affects every user holding the role.')
                ->permission('permission_matrix:update')
                ->params(Param::string('role', 'Role name.', required: true), Param::array('grant', 'Permission names to grant, e.g. clearance:print.', 'string'), Param::array('revoke', 'Permission names to revoke.', 'string'))
                ->destructive(fn (User $actor, array $args): array => ['role' => $args['role'], 'grant' => $args['grant'] ?? [], 'revoke' => $args['revoke'] ?? []])
                ->covers(PermissionMatrixService::class.'::update')
                ->handler(function (User $actor, array $args): array {
                    $permissions = app(PermissionMatrixService::class)->update($actor, $args['role'], $args['grant'] ?? [], $args['revoke'] ?? []);

                    return ['summary' => "Updated {$args['role']}: now ".count($permissions).' permission(s).', 'data' => $permissions];
                }),
        ];
    }
}
