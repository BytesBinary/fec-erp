<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Renames the human-named placeholder roles to the canonical snake_case role
 * keys used by the RBAC layer (ids and user assignments are preserved) and
 * creates the roles that did not exist before. See docs/DECISIONS.md D-005.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const RENAMES = [
        'Principal' => 'principal',
        'Department Head' => 'department_head',
        'Teacher' => 'teacher',
        'Student' => 'student',
        'Librarian' => 'librarian',
        'Hall Provost' => 'hall_provost',
    ];

    /**
     * @var list<string>
     */
    private const CREATED = ['admin_office', 'head_of_institution'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            $this->rename($from, $to);
        }

        foreach (self::CREATED as $key) {
            $exists = DB::table('roles')->where('name', $key)->where('guard_name', 'web')->exists();

            if (! $exists) {
                DB::table('roles')->insert([
                    'name' => $key,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $createdIds = DB::table('roles')->whereIn('name', self::CREATED)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->whereIn('role_id', $createdIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $createdIds)->delete();
        DB::table('roles')->whereIn('id', $createdIds)->delete();

        foreach (self::RENAMES as $from => $to) {
            $this->rename($to, $from);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Names are compared in PHP so case-insensitive MySQL collations do not
     * mistake "Teacher" for "teacher".
     */
    private function rename(string $from, string $to): void
    {
        $roles = DB::table('roles')->where('guard_name', 'web')->get(['id', 'name']);

        $source = $roles->first(fn (object $role): bool => $role->name === $from);
        $targetExists = $roles->contains(fn (object $role): bool => $role->name === $to);

        if ($source === null || $targetExists) {
            return;
        }

        DB::table('roles')->where('id', $source->id)->update(['name' => $to, 'updated_at' => now()]);
    }
};
