<?php

namespace App\Mcp\Domains;

use App\Exceptions\Domain\ValidationException;
use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Profile\ProfileService;
use App\Services\Security\SessionTracker;
use App\Services\Users\RoleService;
use App\Services\Users\UserService;

/**
 * Identity and self-service. Available to every role; they always act on the
 * signed-in user only, and keep working while a student's profile is
 * incomplete (spec §6).
 */
final class MeTools
{
    /**
     * Profile fields a student may change through `me_update_profile`.
     */
    public const STUDENT_FIELDS = [
        'full_name_certificate', 'father_name', 'mother_name', 'date_of_birth', 'phone', 'email', 'present_address', 'permanent_address',
        'guardian_name', 'guardian_phone', 'blood_group', 'nid_or_birth_reg', 'is_residential', 'hall_id', 'emergency_contact_name', 'emergency_contact_phone',
    ];

    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        $studentParams = array_map(
            fn (string $field): Param => match ($field) {
                'is_residential' => Param::boolean($field, 'Students only: true if living in a hall.'),
                'hall_id' => Param::integer($field, 'Students only: hall id when residential.'),
                default => Param::string($field, 'Students only: '.str_replace('_', ' ', $field).'.'),
            },
            self::STUDENT_FIELDS,
        );

        return [
            ToolDefinition::make('me_get_profile', 'My profile', 'Get the signed-in user\'s own profile. Students also get profile completion progress and the list of missing or invalid required fields. Use this first to learn who you are acting as.')
                ->covers(ProfileService::class.'::mine', UserService::class.'::me')
                ->handler(function (User $actor): array {
                    $profiles = app(ProfileService::class);

                    if ($profiles->studentOf($actor) !== null) {
                        $described = $profiles->mine($actor);

                        return ['summary' => "Profile is {$described['progress']['percent']}% complete.", 'data' => [
                            'user' => ['id' => $actor->id, 'name' => $actor->name, 'email' => $actor->email],
                            'student' => ['roll_number' => $described['student']->roll_number, 'department_id' => $described['student']->department_id],
                            'profile' => $described['profile']->toArray(),
                            'progress' => $described['progress'],
                            'missing' => $described['problems'],
                        ]];
                    }

                    $user = app(UserService::class)->me($actor);

                    return ['summary' => "You are {$user->name}.", 'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'roles' => $user->roles->pluck('name')->all()]];
                }),

            ToolDefinition::make('me_update_profile', 'Update my profile', 'Update the signed-in user\'s own details. Staff can change name and email. Students can change their profile fields (some fields lock after a clearance request). Send only what changes. Passwords, 2FA and photos are web-only.')
                ->params(Param::string('name', 'Your display name (staff).'), ...$studentParams)
                ->write(idempotent: true)
                ->covers(ProfileService::class.'::save', UserService::class.'::updateOwnProfile')
                ->handler(function (User $actor, array $args): array {
                    $profiles = app(ProfileService::class);
                    $student = $profiles->studentOf($actor);

                    if ($student !== null) {
                        $profile = $profiles->save($actor, $student, array_intersect_key($args, array_flip(self::STUDENT_FIELDS)));

                        return ['summary' => $profile->profile_completed_at ? 'Profile saved and complete.' : 'Profile saved; some required fields are still missing.', 'data' => $profile->toArray()];
                    }

                    if (array_intersect_key($args, ['name' => 1, 'email' => 1]) === []) {
                        throw new ValidationException('Send name and/or email to update.');
                    }

                    $user = app(UserService::class)->updateOwnProfile($actor, $args);

                    return ['summary' => 'Your details were updated.', 'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]];
                }),

            ToolDefinition::make('me_list_permissions', 'My permissions', 'List the signed-in user\'s roles, the scope of each role (department, hall or course ids) and every permission they hold. Use it to explain why something is not allowed.')
                ->covers(RoleService::class.'::rolesOf')
                ->handler(function (User $actor): array {
                    $roles = app(RoleService::class)->rolesOf($actor, $actor);

                    return ['summary' => count($roles).' role(s).', 'data' => ['roles' => $roles, 'permissions' => $actor->getAllPermissions()->pluck('name')->sort()->values()->all()]];
                }),

            ToolDefinition::make('me_list_sessions', 'My login sessions', 'List the devices the signed-in user is logged in on (read-only). Logging devices out, 2FA and integrations are web-only actions.')
                ->covers(SessionTracker::class.'::activeFor')
                ->handler(function (User $actor): array {
                    $sessions = app(SessionTracker::class)->activeFor($actor)->map(fn (UserSession $session): array => [
                        'device' => $session->device_label, 'ip' => $session->ip, 'last_active_at' => $session->last_active_at?->toIso8601String(), 'trusted_for_2fa' => $session->isTrusted(),
                    ])->all();

                    return ['summary' => count($sessions).' active session(s).', 'data' => $sessions];
                }),
        ];
    }
}
