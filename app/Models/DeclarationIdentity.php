<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One (user_id, type) identity, tied to the person who owns it.
 *
 * See the create_declaration_identities migration for the grouping rationale.
 * This is a read-side link only -- it never mirrors or replaces a
 * coi_declarations row; declarations keep their own (user_id, type) unchanged.
 */
class DeclarationIdentity extends Model
{
    public const SOURCE_SELF = 'self';

    public const SOURCE_REHIRE = 'hris_rehire';

    public const SOURCE_CONVERSION = 'non_employee_conversion';

    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_NON_EMPLOYEE = 'non_employee';

    protected $connection = 'mysql';

    protected $table = 'declaration_identities';

    protected $guarded = [];

    protected $casts = [
        'person_id' => 'integer',
        'user_id' => 'integer',
        'is_primary' => 'boolean',
        'linked_by' => 'integer',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
