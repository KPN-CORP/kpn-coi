<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Master Settings (form templates and form types) is limited to the accounts
 * whose email is listed in FORM_MASTER_ADMIN_EMAILS (config
 * forms.master_admin_emails). See EmailListedAccess for the rules.
 */
class FormMasterAccess extends EmailListedAccess
{
    /** Permission name prefix of the Master Settings module. */
    public const PREFIX = 'form_master.';

    protected static function prefix(): string
    {
        return self::PREFIX;
    }

    protected static function configKey(): string
    {
        return 'forms.master_admin_emails';
    }

    public static function isMasterPermission(string $name): bool
    {
        return static::isModulePermission($name);
    }
}
