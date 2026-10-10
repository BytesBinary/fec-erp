<?php

namespace Database\Seeders;

use App\Enums\DesignationType;
use App\Enums\ScopeType;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Hall;
use App\Models\RoleScope;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StaffSeeder extends Seeder
{
    /**
     * Institution-wide staff. Staff records require a department, so these
     * are recorded against the institution's first department even though
     * the role itself isn't department-scoped.
     *
     * Each entry: [name, email, employeeId, designationName, roleName]
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private const STAFF = [
        ['Md. Anisur Rahman', 'librarian@fec.edu.bd', 'STF001', 'Librarian', 'librarian'],
        ['Shirin Akter', 'provost@fec.edu.bd', 'STF002', 'Hall Provost', 'hall_provost'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departmentId = Department::query()->orderBy('id')->value('id');

        if ($departmentId === null) {
            return;
        }

        $designations = Designation::where('type', DesignationType::Staff)->pluck('id', 'name');

        foreach (self::STAFF as [$name, $email, $employeeId, $designationName, $roleName]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                ]
            );

            Staff::updateOrCreate(
                ['employee_id' => $employeeId],
                [
                    'user_id' => $user->id,
                    'department_id' => $departmentId,
                    'designation_id' => $designations[$designationName] ?? null,
                    'joining_date' => '2020-01-01',
                    'phone' => null,
                ]
            );

            $user->syncRoles($roleName);

            $firstHallId = Hall::query()->orderBy('id')->value('id');

            if ($roleName === 'hall_provost' && $firstHallId !== null) {
                RoleScope::firstOrCreate([
                    'user_id' => $user->id,
                    'role_id' => Role::findByName('hall_provost', 'web')->id,
                    'scope_type' => ScopeType::Hall->value,
                    'scope_id' => $firstHallId,
                ]);
            }
        }
    }
}
