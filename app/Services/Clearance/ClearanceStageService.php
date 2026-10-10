<?php

namespace App\Services\Clearance;

use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\ClearanceStage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Super admin configuration of the approval chain (`clearance_stage_config_*`).
 */
class ClearanceStageService
{
    public function __construct(protected Authorizer $authorizer, protected AuditLogger $audit) {}

    /**
     * @return Collection<int, ClearanceStage>
     */
    public function all(User $actor): Collection
    {
        $this->authorizer->authorize($actor, 'clearance_stage:manage');

        return ClearanceStage::query()->with('approverRole')->orderBy('order')->orderBy('id')->get();
    }

    public function toggle(User $actor, int $id, string $field): ClearanceStage
    {
        $this->authorizer->authorize($actor, 'clearance_stage:manage');

        if (! in_array($field, ['active', 'skippable'], true)) {
            throw new ValidationException("Unknown stage setting [{$field}].");
        }

        $stage = ClearanceStage::query()->find($id) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'clearance stage']));

        $this->audit->as($actor, fn () => $stage->update([$field => ! $stage->{$field}]));

        return $stage;
    }

    /**
     * Swaps the stage with its neighbour.
     */
    public function move(User $actor, int $id, int $direction): void
    {
        $this->authorizer->authorize($actor, 'clearance_stage:manage');

        $stages = ClearanceStage::query()->orderBy('order')->orderBy('id')->get()->values();
        $index = $stages->search(fn (ClearanceStage $stage): bool => $stage->getKey() === $id);

        if ($index === false) {
            throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'clearance stage']));
        }

        $target = $index + ($direction < 0 ? -1 : 1);

        if (! isset($stages[$target])) {
            return;
        }

        $this->audit->as($actor, fn () => DB::transaction(function () use ($stages, $index, $target): void {
            $order = $stages->pluck('id')->all();
            [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

            foreach ($order as $position => $stageId) {
                ClearanceStage::query()->whereKey($stageId)->update(['order' => $position + 1]);
            }
        }));
    }
}
