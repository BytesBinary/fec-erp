<?php

namespace App\Services\Clearance;

use App\Models\ClearanceStage;
use App\Models\HallResidency;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Orders the configured chain and decides which stages a student skips
 * (spec §8.2): a skippable hall stage is skipped for non-residents.
 */
class StageResolver
{
    /**
     * @return Collection<int, ClearanceStage>
     */
    public function chain(): Collection
    {
        return ClearanceStage::query()->inChain()->with('approverRole')->get();
    }

    public function shouldSkip(ClearanceStage $stage, Student $student): bool
    {
        if (! $stage->skippable) {
            return false;
        }

        return match ($stage->scope_rule) {
            'hall' => ! HallResidency::query()->current()->where('student_id', $student->getKey())->exists(),
            default => false,
        };
    }

    /**
     * Stages after `$after` (all of them when null), in chain order.
     *
     * @return Collection<int, ClearanceStage>
     */
    public function stagesAfter(?ClearanceStage $after): Collection
    {
        return $this->chain()->filter(function (ClearanceStage $stage) use ($after): bool {
            if ($after === null) {
                return true;
            }

            return [$stage->order, $stage->id] > [$after->order, $after->id];
        })->values();
    }
}
