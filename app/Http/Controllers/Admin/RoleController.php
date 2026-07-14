<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Inertia\Response;
use App\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\BusinessUnit;
use App\Models\Companies;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Faker\Provider\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->latest()
            ->paginate(10);

        $userIdsByRole = DB::connection('mysql')
            ->table('model_has_roles')
            ->where('model_type', User::class)
            ->get()
            ->groupBy('role_id');

        $users = User::query()
            ->select('id', 'name', 'email')
            ->get()
            ->keyBy('id');

        $roles->getCollection()->transform(function ($role) use ($userIdsByRole, $users) {

            $role->setRelation(
                'users',
                collect($userIdsByRole[$role->id] ?? [])
                    ->map(fn ($pivot) => $users[$pivot->model_id] ?? null)
                    ->filter()
                    ->values()
            );

            return $role;
        });

        return Inertia::render(
            'Admin/Roles',
            [
                'roles' => RoleResource::collection(
                    $roles
                ),

                'users' => User::query()
                    ->select(
                        'id',
                        'name',
                        'email'
                    )
                    ->orderBy('name')
                    ->get(),

                'availablePermissions' => Permission::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ]),

                'workAreas' => Employee::query()
                    ->select('office_area')
                    ->whereNotNull('office_area')
                    ->where('office_area', '!=', '')
                    ->distinct()
                    ->orderBy('office_area')
                    ->get()
                    ->map(fn ($item) => [
                        'code' => $item->office_area,
                        'name' => $item->office_area,
                    ])
                    ->values(),

                'groupCompanies' => BusinessUnit::query()
                    ->select(
                        'kode_bisnis',
                        'nama_bisnis'
                    )
                    ->whereNotNull('kode_bisnis')
                    ->orderBy('nama_bisnis')
                    ->get()
                    ->map(fn ($item) => [
                        'code' => $item->kode_bisnis,
                        'name' => $item->nama_bisnis,
                    ])
                    ->values(),

                'contributionLevels' => Companies::query()
                    ->select(
                        'contribution_level_code',
                        'contribution_level'
                    )
                    ->whereNotNull('contribution_level_code')
                    ->distinct()
                    ->orderBy('contribution_level')
                    ->get()
                    ->map(fn ($item) => [
                        'code' => $item->contribution_level_code,
                        'name' => $item->contribution_level,
                    ])
                    ->values(),
            ]
        );
    }

    public function store(
        Request $request
        ) {
        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'unique:roles,name',
                ],

                'restrictions' => [
                    'nullable',
                    'array',
                ],

                'restrictions.work_area_code' => [
                    'nullable',
                    'array',
                ],

                'restrictions.group_company' => [
                    'nullable',
                    'array',
                ],

                'restrictions.contribution_level_code' => [
                    'nullable',
                    'array',
                ],

                'permissions' => [
                    'array',
                ],
            ]);

        $role = Role::create([
            'name' => $validated['name'],
            'restrictions' => [
                'work_area_code'
                    => $request->restrictions['work_area_code'] ?? [],

                'group_company'
                    => $request->restrictions['group_company'] ?? [],

                'contribution_level_code'
                    => $request->restrictions['contribution_level_code'] ?? [],
            ],
        ]);

        $role->syncPermissions(
            $validated['permissions'] ?? []
        );

        return back()->with(
            'success',
            'Role created successfully.'
        );
    }

    public function update(
        Request $request,
        Role $role
    ) {
        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'unique:roles,name,' .
                    $role->id,
                ],

                'restrictions' => [
                    'nullable',
                    'array',
                ],

                'restrictions.work_area_code' => [
                    'nullable',
                    'array',
                ],

                'restrictions.group_company' => [
                    'nullable',
                    'array',
                ],

                'restrictions.contribution_level_code' => [
                    'nullable',
                    'array',
                ],
                'permissions' => [
                    'array',
                ],
            ]);

        $role->update([
            'name' => $request->name,

            'restrictions' => [
                'work_area_code'
                    => $request->restrictions['work_area_code'] ?? [],

                'group_company'
                    => $request->restrictions['group_company'] ?? [],

                'contribution_level_code'
                    => $request->restrictions['contribution_level_code'] ?? [],
            ],
        ]);

        $role->syncPermissions(
            $validated['permissions'] ?? []
        );

        return back()->with(
            'success',
            'Role updated successfully.'
        );
    }

    public function destroy(
        Role $role
    ) {
        if (
            in_array(
                $role->name,
                [
                    'Super Admin',
                    'Employee',
                ]
            )
        ) {
            return back()->with(
                'error',
                'System role cannot be deleted.'
            );
        }

        $role->delete();

        return back()->with(
            'success',
            'Role deleted successfully.'
        );
    }

    public function assignUsers(
        Request $request,
        Role $role
    ): RedirectResponse {

        $validated = $request->validate([
            'users' => [
                'array',
            ],
        ]);

        User::query()
            ->whereIn(
                'id',
                $validated['users']
            )
            ->get()
            ->each(function ($user) use ($role) {

                $user->syncRoles([
                    $role->name,
                ]);

            });

        return back()->with(
            'success',
            'Role assignments updated.'
        );
    }
}