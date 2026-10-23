<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/** Idempoten: aman dijalankan ulang setelah menambah permission baru. */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::ALL as $name => [$group, $label, $scope]) {
            Permission::updateOrCreate(['name' => $name], compact('group', 'label', 'scope'));
        }

        foreach (Permissions::ROLES as $slug => [$name, $scope, $perms]) {
            $role = Role::updateOrCreate(['tenant_id' => null, 'slug' => $slug], ['name' => $name, 'scope' => $scope, 'is_system' => true]);
            $names = $perms === ['*'] ? Permissions::forScope($scope) : $perms;
            $role->permissions()->sync(Permission::whereIn('name', $names)->where('scope', $scope)->pluck('id'));
        }
    }
}
