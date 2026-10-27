<?php

declare(strict_types=1);

use App\Models\FormType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Form dashboards and reports are granted per form type, not as one module.
 *
 * Each form type gets its own permission (form_type.{code}, see
 * FormType::permissionName()), shown in Role Management under the form's
 * name. Roles that held the old generic form_dashboard.view /
 * form_report.view / form_report.export get every form type, so nobody loses
 * access; the generic ones are then removed. Like the earlier permissions
 * migration, this runs where seeders never do (cPanel deploys). Safe to
 * re-run.
 */
return new class extends Migration
{
    private const OLD = ['form_dashboard.view', 'form_report.view', 'form_report.export'];

    public function up(): void
    {
        if (! Schema::hasTable('form_types')) {
            return;
        }

        $types = FormType::query()->get();

        foreach ($types as $type) {
            $type->ensurePermission();
        }

        $names = $types->map(fn (FormType $type) => $type->permissionName())->all();

        $old = Permission::query()->whereIn('name', self::OLD)->where('guard_name', 'web')->get();

        if ($names !== [] && $old->isNotEmpty()) {
            Role::query()
                ->whereHas('permissions', fn ($query) => $query->whereIn('id', $old->pluck('id')))
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($names));
        }

        $old->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Granting per form type cannot be folded back into one switch
        // without guessing; leave it.
    }
};
