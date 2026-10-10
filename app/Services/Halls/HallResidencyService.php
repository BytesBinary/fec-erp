<?php

namespace App\Services\Halls;

use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\Hall;
use App\Models\HallDue;
use App\Models\HallResidency;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\ResourceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Minimal hall module needed by clearance (spec §4.2): which student lives in
 * which hall, and the dues a provost records against them.
 */
class HallResidencyService
{
    public function __construct(protected Authorizer $authorizer, protected AuditLogger $audit) {}

    public function assign(User $actor, Student $student, Hall $hall, ?string $room = null): HallResidency
    {
        $this->authorizer->authorize($actor, 'hall:assign_student', ResourceScope::forHall($hall->getKey()));

        return $this->audit->as($actor, fn (): HallResidency => DB::transaction(function () use ($student, $hall, $room): HallResidency {
            HallResidency::query()->current()->where('student_id', $student->getKey())->update(['ended_on' => today()]);

            return HallResidency::query()->create([
                'student_id' => $student->getKey(),
                'hall_id' => $hall->getKey(),
                'room' => $room,
                'assigned_on' => today(),
            ]);
        }));
    }

    public function vacate(User $actor, HallResidency $residency): HallResidency
    {
        $this->authorizer->authorize($actor, 'hall:assign_student', ResourceScope::forHall($residency->hall_id));

        $this->audit->as($actor, fn () => $residency->update(['ended_on' => today()]));

        return $residency;
    }

    /**
     * Current residents of halls the actor manages.
     *
     * @return Builder<HallResidency>
     */
    public function residents(User $actor): Builder
    {
        $this->authorizer->authorize($actor, 'hall:list');

        return $this->authorizer->scopeFor($actor, 'hall:assign_student')
            ->constrain(HallResidency::query()->current()->with(['student.user', 'hall']), ['hall' => 'hall_id']);
    }

    public function currentFor(Student $student): ?HallResidency
    {
        return HallResidency::query()->current()->with('hall')->where('student_id', $student->getKey())->first();
    }

    /**
     * Dues of one student, visible to the student, provosts of their hall and admin.
     *
     * @return Collection<int, HallDue>
     */
    public function duesOf(User $actor, Student $student, bool $openOnly = false): Collection
    {
        $this->authorizeDuesAccess($actor, $student);

        return HallDue::query()->with('hall')->where('student_id', $student->getKey())->when($openOnly, fn (Builder $query) => $query->open())->orderBy('id')->get();
    }

    public function recordDue(User $actor, Student $student, string $description, float $amount): HallDue
    {
        $residency = $this->currentFor($student) ?? throw new NotFoundException('The student does not live in a hall.');
        $this->authorizer->authorize($actor, 'hall_dues:manage', ResourceScope::forHall($residency->hall_id));

        if ($amount <= 0 || trim($description) === '') {
            throw new ValidationException('A due needs a description and a positive amount.');
        }

        return $this->audit->as($actor, fn (): HallDue => HallDue::query()->create([
            'student_id' => $student->getKey(),
            'hall_id' => $residency->hall_id,
            'description' => $description,
            'amount' => $amount,
        ]));
    }

    public function settleDue(User $actor, HallDue $due): HallDue
    {
        $this->authorizer->authorize($actor, 'hall_dues:manage', $due);

        if ($due->settled_at !== null) {
            throw new ConflictException('This due is already settled.');
        }

        $this->audit->as($actor, fn () => $due->update(['settled_at' => now()]));

        return $due;
    }

    protected function authorizeDuesAccess(User $actor, Student $student): void
    {
        if ($student->user_id === $actor->getKey()) {
            $this->authorizer->authorize($actor, 'clearance:view', ResourceScope::forOwner($actor->getKey()));

            return;
        }

        $hallId = $this->currentFor($student)?->hall_id;
        $scope = ResourceScope::forHall($hallId)->merge(ResourceScope::forDepartment($student->department_id));

        $this->authorizer->authorize($actor, 'hall_dues:view', $scope);
    }
}
