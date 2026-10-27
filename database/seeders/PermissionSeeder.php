<?php

namespace Database\Seeders;

use App\Models\FormType;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

            'dashboard.view',

            'report.view',
            'report.export',
            'report.download_pdf',

            'credential.view',
            'credential.create',
            'credential.update',
            'credential.delete',

            'role.view',
            'role.create',
            'role.update',
            'role.delete',

            'declaration.review',
            'declaration.approve',
            'declaration.reject',

            'reminder.send',

            'form_master.view',
            'form_master.create',
            'form_master.update',
            'form_master.delete',

            'import.view',
            'import.create',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
            ]);
        }

        // Looked up by name only, so a role whose scope was changed later is
        // found instead of duplicated. New roles start unrestricted: the
        // deployed roles.restrictions column is NOT NULL.
        // App\Models\Role, not Spatie's: it casts restrictions to JSON.
        $role = function (string $name, string $scope): Role {
            $role = Role::query()->firstOrNew(['name' => $name, 'guard_name' => 'web']);

            if (! $role->exists) {
                $role->forceFill(['data_scope' => $scope, 'restrictions' => []])->save();
            }

            return $role;
        };

        $superAdmin = $role('Super Admin', 'all');

        $superAdmin->forceFill(['data_scope' => 'all'])->save();

        // Each form type is its own permission (its dashboard and report);
        // created with the type, and here for any that exist already.
        if (Schema::hasTable('form_types')) {
            FormType::query()->get()->each(fn (FormType $type) => Permission::findOrCreate($type->permissionName(), 'web'));
        }

        $superAdmin->syncPermissions(
            Permission::all()
        );

        $role('COI Admin', 'all');
        $role('BU Admin', 'business_unit');
        $role('Manager', 'direct_reportee');
        $role('Employee', 'self');
    }
}
