<?php

namespace App\Services\Results;

use App\Exceptions\Domain\ValidationException;
use App\Models\GradingScale;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Collection;

/**
 * Marks → letter + grade point from the live `grading_scales` table.
 */
class GradingScaleService
{
    /** @var Collection<int, GradingScale>|null */
    protected ?Collection $bands = null;

    public function __construct(protected Authorizer $authorizer) {}

    /**
     * @return Collection<int, GradingScale>
     */
    public function bands(): Collection
    {
        return $this->bands ??= GradingScale::query()
            ->where(fn ($query) => $query->whereNull('active_from')->orWhere('active_from', '<=', today()))
            ->orderByDesc('min_mark')
            ->get();
    }

    public function forget(): void
    {
        $this->bands = null;
    }

    /**
     * @return array{letter: string, point: float}
     */
    public function gradeFor(float $marks): array
    {
        if ($marks < 0 || $marks > 100) {
            throw new ValidationException('Marks must be between 0 and 100.');
        }

        $band = $this->bands()->first(fn (GradingScale $band): bool => $marks >= $band->min_mark && $marks <= $band->max_mark)
            ?? $this->bands()->first(fn (GradingScale $band): bool => $marks >= $band->min_mark);

        if ($band === null) {
            throw new ValidationException('No grading band covers these marks.');
        }

        return ['letter' => $band->letter, 'point' => $band->grade_point];
    }

    /**
     * @return Collection<int, GradingScale>
     */
    public function scale(User $actor): Collection
    {
        return $this->bands();
    }

    /**
     * Replaces the whole scale (super admin).
     *
     * @param  list<array{min_mark: float, max_mark: float, letter: string, grade_point: float}>  $bands
     */
    public function replace(User $actor, array $bands): void
    {
        $this->authorizer->authorize($actor, 'grading_scale:manage');

        if ($bands === []) {
            throw new ValidationException('The grading scale needs at least one band.');
        }

        GradingScale::query()->delete();

        foreach ($bands as $band) {
            GradingScale::query()->create($band + ['active_from' => today()]);
        }

        $this->forget();

        app(\App\Services\Notifications\NotificationEvents::class)->emit('grading_scale.changed', ['actor' => $actor->name, 'link' => url('/settings/grading-scale')], null);
    }
}
