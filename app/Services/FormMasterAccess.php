<?php

declare(strict_types=1);

namespace App\Services;

use Spatie\Permission\Models\Permission;

/**
 * Master Settings (form templates and form types) is limited to the accounts
 * whose email is listed in FORM_MASTER_ADMIN_EMAILS (config
 * forms.master_admin_emails):
 *
 * - only they see and open it (menu, EnsureFormMasterAdmin on the routes),
 *   and still need the form_master.* permission for each action;
 * - only they can grant or take away form_master.* in Role Management.
 *
 * An empty list closes Master Settings to everyone; existing grants stay.
 */
class FormMasterAccess
{
    /** Permission name prefix of the Master Settings module. */
    public const PREFIX = 'form_master.';

    public static function isListed(?object $user): bool
    {
        $email = mb_strtolower(trim((string) ($user->email ?? '')));

        return $email !== '' && in_array($email, self::emails(), true);
    }

    /**
     * @return list<string> lower-cased
     */
    public static function emails(): array
    {
        return array_values(array_unique(array_map(
            fn ($email) => mb_strtolower(trim((string) $email)),
            config('forms.master_admin_emails', [])
        )));
    }

    public static function isMasterPermission(string $name): bool
    {
        return str_starts_with($name, self::PREFIX);
    }

    /**
     * The permissions to save on a role. Someone not on the list cannot
     * change the Master Settings ones: whatever the request says, the role
     * keeps the form_master.* permissions it already had.
     *
     * @param  list<string>  $requested
     * @param  list<string>  $current  the role's permission names before saving
     * @return list<string>
     */
    public static function permissionsToSave(?object $user, array $requested, array $current): array
    {
        if (self::isListed($user)) {
            return array_values($requested);
        }

        return array_values(array_unique([
            ...array_filter($requested, fn ($name) => ! self::isMasterPermission((string) $name)),
            ...array_filter($current, fn ($name) => self::isMasterPermission((string) $name)),
        ]));
    }

    /**
     * Drop the Master Settings permissions from the Role Management list for
     * someone who may not assign them, so the module is not shown at all.
     */
    public static function visiblePermissions(?object $user, $permissions)
    {
        return self::isListed($user)
            ? $permissions
            : $permissions->reject(fn (Permission $permission) => self::isMasterPermission($permission->name))->values();
    }
}
