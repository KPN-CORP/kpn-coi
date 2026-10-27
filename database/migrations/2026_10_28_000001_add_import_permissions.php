<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Import Center permissions. Also in PermissionSeeder, but deploys never run
 * seeders (see 2026_10_15_000000_add_form_permissions). Idempotent.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'import.view',
        'import.create',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Super Admin holds every permission (see PermissionSeeder).
        Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->get()
            ->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
