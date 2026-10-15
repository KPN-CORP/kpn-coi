<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Forms admin routes are guarded by these permissions. They are also in
 * PermissionSeeder, but the cPanel deploy only ever runs migrations (by hand),
 * never seeders -- without this, every Forms admin page would 403 after a
 * deploy, Super Admin included, and Role Management would offer nothing to
 * grant. Idempotent: safe where the seeder already ran.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'form_dashboard.view',
        'form_master.view',
        'form_master.create',
        'form_master.update',
        'form_master.delete',
        'form_report.view',
        'form_report.export',
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
