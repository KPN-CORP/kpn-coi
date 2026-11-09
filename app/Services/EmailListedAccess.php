<?php

declare(strict_types=1);

namespace App\Services;

use Spatie\Permission\Models\Permission;

/**
 * A menu module limited to the accounts whose email is listed in .env, on
 * top of its role permissions (FormMasterAccess, ImportCenterAccess):
 *
 * - only listed accounts see and open it (menu flag, route middleware),
 *   and still need the module's permission for each action;
 * - only they can grant or take away its permissions in Role Management.
 *
 * An empty list closes the module to everyone; existing grants stay.
 */
abstract class EmailListedAccess
{
    /** Permission name prefix of the module, e.g. "form_master.". */
    abstract protected static function prefix(): string;

    /** Config key holding the listed emails. */
    abstract protected static function configKey(): string;

    public static function isListed(?object $user): bool
    {
        $email = mb_strtolower(trim((string) ($user->email ?? '')));

        return $email !== '' && in_array($email, static::emails(), true);
    }

    /**
     * @return list<string> lower-cased
     */
    public static function emails(): array
    {
        return array_values(array_unique(array_map(
            fn ($email) => mb_strtolower(trim((string) $email)),
            config(static::configKey(), [])
        )));
    }

    public static function isModulePermission(string $name): bool
    {
        return str_starts_with($name, static::prefix());
    }

    /**
     * The permissions to save on a role. Someone not on the list cannot
     * change this module's ones: whatever the request says, the role keeps
     * the module permissions it already had.
     *
     * @param  list<string>  $requested
     * @param  list<string>  $current  the role's permission names before saving
     * @return list<string>
     */
    public static function permissionsToSave(?object $user, array $requested, array $current): array
    {
        if (static::isListed($user)) {
            return array_values($requested);
        }

        return array_values(array_unique([
            ...array_filter($requested, fn ($name) => ! static::isModulePermission((string) $name)),
            ...array_filter($current, fn ($name) => static::isModulePermission((string) $name)),
        ]));
    }

    /**
     * Drop this module's permissions from the Role Management list for
     * someone who may not assign them, so the module is not shown at all.
     */
    public static function visiblePermissions(?object $user, $permissions)
    {
        return static::isListed($user)
            ? $permissions
            : $permissions->reject(fn (Permission $permission) => static::isModulePermission($permission->name))->values();
    }
}
