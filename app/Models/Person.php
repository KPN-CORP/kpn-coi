<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One real human, grouping every identity they have held (a non-employee
 * login plus one or more employee stints across rehires). Owns nothing but the
 * surrogate id used as the grouping key on declaration_identities.
 *
 * Lives on the default (app) connection; the identities it groups span two
 * databases, so it is referenced by plain id, never a cross-database FK.
 */
class Person extends Model
{
    protected $connection = 'mysql';

    protected $table = 'persons';

    protected $guarded = [];

    public function identities(): HasMany
    {
        return $this->hasMany(DeclarationIdentity::class);
    }
}
