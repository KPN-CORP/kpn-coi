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
use App\Models\NonEmployee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString() ?: null,

            'sort' => in_array($request->input('sort'), ['name', 'users_count'], true)
                ? $request->input('sort')
                : 'name',

            'direction' => $request->input('direction') === 'desc'
                ? 'desc'
                : 'asc',

            'per_page' => (int) $request->input('per_page', 10),
        ];

        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->when(
                $filters['search'],
                fn ($query, $search) => $query->where('name', 'like', "%{$search}%")
            )
            ->orderBy($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

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

                'filters' => $filters,

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

                // Restrictions are matched against the people tables, so every
                // option below must carry the value those tables actually
                // store -- a code that appears nowhere in them can only ever
                // select zero rows.
                'workAreas' => $this->officeAreaOptions(),

                // employees.group_company / non_employees.group_company hold
                // the unit *name* ("Cement"), never its kode_bisnis ("BU03"),
                // so the name is what a restriction has to be saved as.
                'groupCompanies' => BusinessUnit::query()
                    ->select('nama_bisnis')
                    ->whereNotNull('nama_bisnis')
                    ->orderBy('nama_bisnis')
                    ->get()
                    ->map(fn ($item) => [
                        'code' => $item->nama_bisnis,
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

    /**
     * Office areas across both people tables -- a restriction covers employees
     * and non-employees alike, so an area used only by non-employees still has
     * to be selectable.
     */
    private function officeAreaOptions(): Collection
    {
        $areas = fn (Builder $query) => $query
            ->select('office_area')
            ->whereNotNull('office_area')
            ->where('office_area', '!=', '')
            ->distinct()
            ->pluck('office_area');

        return $areas(Employee::query())
            ->concat($areas(NonEmployee::query()))
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($area) => [
                'code' => $area,
                'name' => $area,
            ]);
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