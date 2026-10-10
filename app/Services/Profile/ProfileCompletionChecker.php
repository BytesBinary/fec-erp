<?php

namespace App\Services\Profile;

use App\Models\ProfileRequiredField;
use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Decides which required profile fields of a student are missing or invalid
 * (spec §6). The required set is data (`profile_required_fields`), so a field
 * added later gates students again on their next request.
 */
class ProfileCompletionChecker
{
    /** @var list<string>|null */
    protected ?array $requiredKeys = null;

    /**
     * @return list<string>
     */
    public function requiredKeys(): array
    {
        return $this->requiredKeys ??= ProfileRequiredField::query()
            ->where('active', true)
            ->where('required', true)
            ->orderBy('id')
            ->pluck('field_key')
            ->all();
    }

    public function forgetCache(): void
    {
        $this->requiredKeys = null;
    }

    /**
     * Required fields that are empty or invalid, as field key => message.
     *
     * @return array<string, string>
     */
    public function problems(Student $student): array
    {
        $student->loadMissing('profile');

        $problems = [];

        foreach ($this->requiredKeys() as $key) {
            $message = $this->problemFor($student, $key);

            if ($message !== null) {
                $problems[$key] = $message;
            }
        }

        return $problems;
    }

    public function isComplete(Student $student): bool
    {
        return $this->problems($student) === [];
    }

    /**
     * @return array{done: int, total: int, percent: int}
     */
    public function progress(Student $student): array
    {
        $total = count($this->requiredKeys());
        $done = $total - count($this->problems($student));

        return ['done' => $done, 'total' => $total, 'percent' => $total === 0 ? 100 : (int) floor($done * 100 / $total)];
    }

    protected function problemFor(Student $student, string $key): ?string
    {
        $profile = $student->profile;
        $label = (string) config("profile.fields.{$key}.label", $key);

        $value = match ($key) {
            'phone' => $student->phone,
            'photo' => $profile?->photo_path,
            'hall' => null,
            default => $profile?->{$key},
        };

        if ($key === 'hall') {
            if ($profile?->is_residential === null) {
                return __('erp.profile.missing', ['field' => $label]);
            }

            return $profile->is_residential && $profile->hall_id === null ? __('erp.profile.missing', ['field' => $label]) : null;
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return __('erp.profile.missing', ['field' => $label]);
        }

        return $this->validate($key, $value, $label);
    }

    protected function validate(string $key, mixed $value, string $label): ?string
    {
        $invalid = __('erp.profile.invalid', ['field' => $label]);

        return match ($key) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : $invalid,
            'phone', 'guardian_phone', 'emergency_contact_phone' => preg_match('/^\+?[0-9]{10,15}$/', (string) $value) === 1 ? null : $invalid,
            'date_of_birth' => $value instanceof Carbon && $value->isPast() && $value->diffInYears(now()) < 100 ? null : $invalid,
            'blood_group' => in_array($value, config('profile.blood_groups'), true) ? null : $invalid,
            'nid_or_birth_reg' => preg_match('/^[0-9]{10}$|^[0-9]{13}$|^[0-9]{17}$/', (string) $value) === 1 ? null : $invalid,
            'full_name_certificate', 'father_name', 'mother_name', 'guardian_name', 'emergency_contact_name' => mb_strlen(trim((string) $value)) >= 2 ? null : $invalid,
            'present_address', 'permanent_address' => mb_strlen(trim((string) $value)) >= 5 ? null : $invalid,
            default => null,
        };
    }
}
