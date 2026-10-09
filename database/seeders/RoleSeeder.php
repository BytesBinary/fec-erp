<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Starting legacy roles for this institution's ERP. These are a scaffold,
     * not a final org chart — adjust permissions live via the Shield "Roles"
     * page in the panel rather than editing this seeder after go-live.
     *
     * The RBAC roles (super_admin, admin_office, department_head, …) and their
     * default permissions are seeded by PermissionSeeder from config/erp.php.
     *
     * @var array<string, list<string>>
     */
    private const ROLES = [
        'Academic Admin' => [
            'ViewAny:Department', 'View:Department', 'Create:Department', 'Update:Department', 'Delete:Department', 'DeleteAny:Department',
            'ViewAny:Designation', 'View:Designation', 'Create:Designation', 'Update:Designation', 'Delete:Designation', 'DeleteAny:Designation',
            'ViewAny:Batch', 'View:Batch', 'Create:Batch', 'Update:Batch', 'Delete:Batch', 'DeleteAny:Batch',
            'ViewAny:Course', 'View:Course', 'Create:Course', 'Update:Course', 'Delete:Course', 'DeleteAny:Course',
            'ViewAny:Staff', 'View:Staff', 'Create:Staff', 'Update:Staff', 'Delete:Staff', 'DeleteAny:Staff',
            'ViewAny:Teacher', 'View:Teacher', 'Create:Teacher', 'Update:Teacher', 'Delete:Teacher', 'DeleteAny:Teacher',
            'ViewAny:Student', 'View:Student', 'Create:Student', 'Update:Student', 'Delete:Student', 'DeleteAny:Student',
            'View:BatchOverview', 'View:BatchDetail', 'View:CreditCountReport',
        ],
        'Exam Coordinator' => [
            'ViewAny:ExamType', 'View:ExamType', 'Create:ExamType', 'Update:ExamType', 'Delete:ExamType', 'DeleteAny:ExamType',
            'ViewAny:ExamHall', 'View:ExamHall', 'Create:ExamHall', 'Update:ExamHall', 'Delete:ExamHall', 'DeleteAny:ExamHall',
            'ViewAny:ExamDuty', 'View:ExamDuty', 'Create:ExamDuty', 'Update:ExamDuty', 'Delete:ExamDuty', 'DeleteAny:ExamDuty',
            'View:ExamDutyReport',
        ],
        'Routine Coordinator' => [
            'ViewAny:Batch', 'View:Batch', 'Update:Batch',
            'View:AssignTeachers', 'View:MasterRoutineReport', 'View:IndividualRoutineReport',
        ],
        'Report Viewer' => [
            'View:MasterRoutineReport', 'View:IndividualRoutineReport', 'View:CreditCountReport', 'View:ExamDutyReport',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::findOrCreate(RoleKey::SuperAdmin->value, 'web');

        foreach (self::ROLES as $name => $permissions) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            $role->syncPermissions(
                Permission::whereIn('name', $permissions)->get()
            );
        }
    }
}
