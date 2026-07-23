<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CoiDeclaration;
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
 * Someone converted from non-employee to employee has two identities: the
 * kpncorp account they now sign into via SSO, and the local non-employee
 * account they used before. The link is `users.employee_id` on the local
 * account, set by the conversion in the credentials screen. Nothing is moved
 * or rewritten when that happens -- the old rows keep their own user_id and
 * type and are simply read through the second identity. Undoing a conversion
 * is therefore just clearing that column.
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

        if (! $user instanceof User) {
            // A non-employee only ever has the one identity. Once converted
            // they sign in through SSO as an employee instead, and this same
            // row is then reached below as a prior identity.
            return [[
                'user_id' => (int) $user->id,
                'type' => 'non_employee',
            ]];
        }

        $identities = [[
            'user_id' => (int) $user->id,
            'type' => 'employee',
        ]];

        foreach ($this->priorNonEmployeeIds($user) as $id) {
            $identities[] = [
                'user_id' => $id,
                'type' => 'non_employee',
            ];
        }

        return $identities;
    }

    /**
     * Local non-employee accounts converted into this employee. Keyed on the
     * HRIS employee_id, the one identifier stable across the move -- the two
     * databases number their users independently, and the email changes to the
     * office domain.
     *
     * @return list<int>
     */
    public function priorNonEmployeeIds(User $user): array
    {
        $employeeId = trim((string) $user->employee_id);

        if ($employeeId === '') {
            return [];
        }

        return NonEmployeeUser::query()
            ->where('employee_id', $employeeId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
