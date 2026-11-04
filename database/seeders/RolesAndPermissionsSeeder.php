<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(PermissionRegistrar $permissionRegistrar): void
    {
        $permissionRegistrar->forgetCachedPermissions();

        DB::transaction(function (): void {
            foreach (PermissionName::cases() as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            foreach (UserRole::cases() as $role) {
                Role::findOrCreate($role, 'web')->syncPermissions($role->permissions());
            }

            User::query()->whereDoesntHave('roles')->eachById(function (User $user): void {
                $user->assignRole(UserRole::RegisteredUser);
            });
        });

        $permissionRegistrar->forgetCachedPermissions();
    }
}
