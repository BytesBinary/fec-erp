<?php

namespace Database\Seeders;

use App\Models\ClearanceStage;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * The default clearance chain (spec §8.2). Existing stages (edited by super
 * admin) are never overwritten.
 */
class ClearanceStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['hall', 'Hall Provost', 1, 'hall_provost', 'hall', true],
            ['library', 'Librarian', 2, 'librarian', 'global', false],
            ['department', 'Department Head', 3, 'department_head', 'department', false],
            ['head', 'Head of Institution', 4, 'head_of_institution', 'global', false],
        ];

        foreach ($stages as [$key, $label, $order, $role, $scope, $skippable]) {
            $roleModel = Role::query()->where(['name' => $role, 'guard_name' => 'web'])->first();

            if ($roleModel === null) {
                continue;
            }

            ClearanceStage::query()->firstOrCreate(['key' => $key], [
                'label' => $label,
                'order' => $order,
                'approver_role_id' => $roleModel->getKey(),
                'scope_rule' => $scope,
                'skippable' => $skippable,
                'active' => true,
            ]);
        }
    }
}
