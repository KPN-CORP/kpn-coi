<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DataScopeService
{
    public function apply(
        Builder $query,
        User $user
    ): Builder {

        $role = $user->roles->first();

        if (! $role) {
            return $query;
        }

        $restrictions =
            $role->restrictions ?? [];

        if (
            ! empty(
                $restrictions['work_area_code']
            )
        ) {
            $query->whereHas(
                'employee',
                fn ($q) =>
                    $q->whereIn(
                        'office_area',
                        $restrictions['work_area_code']
                    )
            );
        }

        if (
            ! empty(
                $restrictions['group_company']
            )
        ) {
            $query->whereHas(
                'employee',
                fn ($q) =>
                    $q->whereIn(
                        'kode_bisnis',
                        $restrictions['group_company']
                    )
            );
        }

        if (
            ! empty(
                $restrictions['contribution_level_code']
            )
        ) {
            $query->whereHas(
                'employee',
                fn ($q) =>
                    $q->whereIn(
                        'contribution_level_code',
                        $restrictions['contribution_level_code']
                    )
            );
        }

        return $query;
    }
}