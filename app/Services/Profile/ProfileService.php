<?php

namespace App\Services\Profile;

use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\Student;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\ResourceScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Reads and writes the student profile (spec §6). Students edit their own;
 * admin office may edit anyone's in scope, including locked fields.
 */
class ProfileService
{
    public function __construct(
        protected Authorizer $authorizer,
        protected ProfileCompletionChecker $checker,
        protected AuditLogger $audit,
    ) {}

    public function studentOf(User $user): ?Student
    {
        return Student::query()->where('user_id', $user->getKey())->first();
    }

    /**
     * The profile of the acting student (created empty on first access).
     *
     * @return array{profile: StudentProfile, student: Student, progress: array{done: int, total: int, percent: int}, problems: array<string, string>}
     */
    public function mine(User $actor): array
    {
        $student = $this->studentOf($actor) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'student profile']));

        return $this->describe($actor, $student);
    }

    /**
     * @return array{profile: StudentProfile, student: Student, progress: array{done: int, total: int, percent: int}, problems: array<string, string>}
     */
    public function describe(User $actor, Student $student): array
    {
        $this->authorizeAccess($actor, $student, 'view');

        $profile = StudentProfile::query()->firstOrNew(['student_id' => $student->getKey()]);

        return [
            'profile' => $profile,
            'student' => $student,
            'progress' => $this->checker->progress($student),
            'problems' => $this->checker->problems($student),
        ];
    }

    /**
     * Saves the given fields (partial updates allowed) and recomputes
     * `profile_completed_at`.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(User $actor, Student $student, array $data): StudentProfile
    {
        $isOwner = $student->user_id === $actor->getKey();

        $this->authorizeAccess($actor, $student, 'update');

        $validated = Validator::make($data, $this->rules(), [], $this->attributeNames())->after(function ($validator) use ($data): void {
            if (($data['is_residential'] ?? null) === true && empty($data['hall_id'])) {
                $validator->errors()->add('hall_id', __('erp.profile.hall_required'));
            }
        });

        if ($validated->fails()) {
            throw new ValidationException(__('erp.errors.validation'), ['errors' => $validated->errors()->toArray()]);
        }

        $values = $validated->validated();

        return $this->audit->as($actor, fn (): StudentProfile => DB::transaction(function () use ($student, $values, $isOwner): StudentProfile {
            $profile = StudentProfile::query()->firstOrCreate(['student_id' => $student->getKey()]);

            $this->assertNotLocked($profile, $values, $isOwner);

            if (array_key_exists('phone', $values)) {
                $student->forceFill(['phone' => $values['phone']])->save();
                unset($values['phone']);
            }

            if (($values['is_residential'] ?? null) === false) {
                $values['hall_id'] = null;
            }

            $profile->fill($values)->save();

            return $this->syncCompletion($student);
        }));
    }

    /**
     * Sets or clears `profile_completed_at` to match the live required set.
     */
    public function syncCompletion(Student $student): StudentProfile
    {
        $student->unsetRelation('profile');
        $profile = StudentProfile::query()->firstOrCreate(['student_id' => $student->getKey()]);
        $complete = $this->checker->isComplete($student);

        if ($complete && $profile->profile_completed_at === null) {
            $profile->forceFill(['profile_completed_at' => now()])->save();
        } elseif (! $complete && $profile->profile_completed_at !== null) {
            $profile->forceFill(['profile_completed_at' => null])->save();
        }

        return $profile;
    }

    /**
     * Freezes name, parents' names and date of birth once a clearance is
     * requested (changing them then needs admin office).
     */
    public function lockFieldsForClearance(Student $student): void
    {
        $profile = StudentProfile::query()->firstOrCreate(['student_id' => $student->getKey()]);
        $profile->forceFill(['locked_fields' => config('profile.locked_after_clearance')])->save();
    }

    /**
     * Super admin: make a profile field required / optional or switch it on or
     * off. Students whose profile no longer satisfies the set are gated again.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\ProfileRequiredField>
     */
    public function requiredFields(User $actor): \Illuminate\Support\Collection
    {
        $this->authorizer->authorize($actor, 'profile_field:manage');

        return \App\Models\ProfileRequiredField::query()->orderBy('id')->get();
    }

    public function configureField(User $actor, string $fieldKey, bool $required, bool $active = true): void
    {
        $this->authorizer->authorize($actor, 'profile_field:manage');

        if (! array_key_exists($fieldKey, config('profile.fields'))) {
            throw new ValidationException("Unknown profile field [{$fieldKey}].");
        }

        $this->audit->as($actor, fn () => \App\Models\ProfileRequiredField::query()->updateOrCreate(['field_key' => $fieldKey], ['required' => $required, 'active' => $active]));
        $this->checker->forgetCache();
    }

    /**
     * True when the student must finish the profile before using the system.
     */
    public function isGated(User $user): bool
    {
        $student = $this->studentOf($user);

        return $student !== null && ! $this->checker->isComplete($student);
    }

    protected function authorizeAccess(User $actor, Student $student, string $action): void
    {
        $permission = $action === 'view' ? 'profile:view' : 'profile:update';

        if ($student->user_id === $actor->getKey()) {
            $this->authorizer->authorize($actor, 'profile:update_own', ResourceScope::forOwner($student->user_id));

            return;
        }

        $this->authorizer->authorize($actor, $permission, $student);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function assertNotLocked(StudentProfile $profile, array $values, bool $isOwner): void
    {
        if (! $isOwner || empty($profile->locked_fields)) {
            return;
        }

        foreach ($profile->locked_fields as $field) {
            if (array_key_exists($field, $values) && (string) $profile->{$field} !== (string) $values[$field] && ! ($profile->{$field} instanceof \Carbon\CarbonInterface && $profile->{$field}->toDateString() === (string) $values[$field])) {
                throw new ForbiddenException(__('erp.profile.locked', ['field' => config("profile.fields.{$field}.label", $field)]), ['field' => $field]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'full_name_certificate' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255'],
            'father_name' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255'],
            'mother_name' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today', 'after:1920-01-01'],
            'phone' => ['sometimes', 'nullable', 'regex:/^\+?[0-9]{10,15}$/'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'present_address' => ['sometimes', 'nullable', 'string', 'min:5', 'max:1000'],
            'permanent_address' => ['sometimes', 'nullable', 'string', 'min:5', 'max:1000'],
            'photo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guardian_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guardian_phone' => ['sometimes', 'nullable', 'regex:/^\+?[0-9]{10,15}$/'],
            'blood_group' => ['sometimes', 'nullable', 'in:'.implode(',', config('profile.blood_groups'))],
            'nid_or_birth_reg' => ['sometimes', 'nullable', 'regex:/^([0-9]{10}|[0-9]{13}|[0-9]{17})$/'],
            'is_residential' => ['sometimes', 'nullable', 'boolean'],
            'hall_id' => ['sometimes', 'nullable', 'integer', 'exists:halls,id'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'regex:/^\+?[0-9]{10,15}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attributeNames(): array
    {
        return collect(config('profile.fields'))->mapWithKeys(fn (array $definition, string $key): array => [$key => $definition['label']])->all();
    }
}
