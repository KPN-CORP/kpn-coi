<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
        ]);

        $superAdmin->update([
            'data_scope' => 'all',
        ]);

        $superAdmin->syncPermissions(
            Permission::all()
        );

        Role::firstOrCreate([
            'name' => 'COI Admin',
            'data_scope' => 'all',
        ]);

        Role::firstOrCreate([
            'name' => 'BU Admin',
            'data_scope' => 'business_unit',
        ]);

        Role::firstOrCreate([
            'name' => 'Manager',
            'data_scope' => 'direct_reportee',
        ]);

        Role::firstOrCreate([
            'name' => 'Employee',
            'data_scope' => 'self',
        ]);
    }
}