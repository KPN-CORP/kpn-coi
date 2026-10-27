<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Key/value settings changed from the admin screens (table app_settings).
 */
class AppSetting extends Model
{
    /** Menu position of Conflict of Interest among the form types' sort_order. */
    public const COI_MENU_ORDER = 'menu.coi_sort_order';

    protected $connection = 'mysql';

    protected $table = 'app_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            return static::query()->find($key)?->value ?? $default;
        } catch (QueryException) {
            // Before the migration has run (e.g. a deploy that has not
            // migrated yet): fall back rather than break every page.
            return $default;
        }
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** COI's menu order; 0 (first, as before) until an admin moves it. */
    public static function coiMenuOrder(): int
    {
        return (int) static::get(self::COI_MENU_ORDER, '0');
    }
}
