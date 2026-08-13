<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CoiDeclaration;
use App\Models\DeclarationIdentity;
use App\Models\NonEmployeeUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which declarations belong to the person currently signed in.
 *
 * A declaration is identified by the pair (user_id, type), never by user_id
 * alone: `type` decides which database user_id points at, and both users
 * tables auto-increment from 1, so employee 21 and non-employee 21 are
 * different people. Matching on user_id alone hands one of them the other's
 * declaration.
 *
 * One human can hold several such identities over time -- a non-employee login,
 * and one or more employee stints across rehires (each rehire mints a fresh
 * employee_id). Those identities are grouped under a person in
 * declaration_identities by IdentityLinkService, which owns the cross-database
 * chain walk. This service only reads that grouping: it maps the signed-in
 * account to its person and returns every identity in the group.
 *
 * Nothing here writes: the grouping is materialised on login and by the
 * identities:backfill command. If an account has no group row yet (a standalone
 * identity, or one not synced), it falls back to "self only" -- the account's
 * own declarations -- so a missing or incomplete grouping can only narrow a
 * person's own history, never lose a declaration or expose someone else's.
 */
class DeclarationScopeService
{
    /**
     * Every (user_id, type) pair the given account may read, current identity
     * first.
     *
     * @return list<array{user_id: int, type: string}>
     */
    public function identitiesFor(User|NonEmployeeUser|null $user): array
    {
        if (! $user) {
            return [];
        }

        $type = $user instanceof User
            ? DeclarationIdentity::TYPE_EMPLOYEE
            : DeclarationIdentity::TYPE_NON_EMPLOYEE;

        $userId = (int) $user->id;

        $self = ['user_id' => $userId, 'type' => $type];

        $group = DeclarationIdentity::query()
            ->where('type', $type)
            ->where('user_id', $userId)
            ->value('person_id');

        if ($group === null) {
            // Not grouped yet: read only this account's own declarations.
            return [$self];
        }

        $identities = DeclarationIdentity::query()
            ->where('person_id', $group)
            // Current identity first, then a stable order for the rest.
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get(['user_id', 'type'])
            ->map(fn (DeclarationIdentity $identity) => [
                'user_id' => (int) $identity->user_id,
                'type' => $identity->type,
            ])
            ->all();

        // Defensive: if the group somehow omits the signed-in account, still
        // let them read their own declarations.
        $hasSelf = collect($identities)->contains(
            fn ($identity) => $identity['user_id'] === $userId && $identity['type'] === $type
        );

        if (! $hasSelf) {
            array_unshift($identities, $self);
        }

        return $identities;
    }

    /**
     * Narrow a coi_declarations query to what this account may read.
     */
    public function applyOwnership(
        Builder $query,
        User|NonEmployeeUser|null $user
    ): Builder {
        $identities = $this->identitiesFor($user);

        if ($identities === []) {
            // Signed out, or an account with no identity at all: match nothing
            // rather than falling through to every declaration in the table.
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            function (Builder $scoped) use ($identities) {
                foreach ($identities as $identity) {
                    $scoped->orWhere(
                        fn (Builder $inner) => $inner
                            ->where('user_id', $identity['user_id'])
                            ->where('type', $identity['type'])
                    );
                }
            }
        );
    }

    public function owns(
        User|NonEmployeeUser|null $user,
        CoiDeclaration $declaration
    ): bool {
        foreach ($this->identitiesFor($user) as $identity) {
            if (
                (int) $declaration->user_id === $identity['user_id']
                && $declaration->type === $identity['type']
            ) {
                return true;
            }
        }

        return false;
    }
}
