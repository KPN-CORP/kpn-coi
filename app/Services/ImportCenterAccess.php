<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Import Center is limited to the accounts whose email is listed in
 * IMPORT_CENTER_ADMIN_EMAILS (config forms.import_admin_emails), the same way
 * as Master Settings. See EmailListedAccess for the rules.
 */
class ImportCenterAccess extends EmailListedAccess
{
    /** Permission name prefix of the Import Center module. */
    public const PREFIX = 'import.';

    protected static function prefix(): string
    {
        return self::PREFIX;
    }

    protected static function configKey(): string
    {
        return 'forms.import_admin_emails';
    }
}
