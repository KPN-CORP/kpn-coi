<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\BusinessUnit;
use App\Models\Companies;
use App\Models\Employee;
use App\Models\NonEmployee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
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
            ->select('id', 'name', 'email', 'employee_id')
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
                        'email',
                        'employee_id'
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
                'workAreas' => $this->workAreaOptions(),

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
     * Work areas across both people tables -- a restriction covers employees
     * and non-employees alike, so a code used only by non-employees still has
     * to be selectable. The value is work_area_code (the unique key the scope
     * matches on); the label is the readable area from the locations master,
     * falling back to the bare code when a code has no locations row.
     */
    private function workAreaOptions(): Collection
    {
        $codes = fn (Builder $query) => $query
            ->select('work_area_code')
            ->whereNotNull('work_area_code')
            ->where('work_area_code', '!=', '')
            ->distinct()
            ->pluck('work_area_code');

        $areaByCode = DB::connection('kpncorp')
            ->table('locations')
            ->whereNotNull('work_area')
            ->pluck('area', 'work_area');

        return $codes(Employee::query())
            ->concat($codes(NonEmployee::query()))
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($code) => [
                'code' => $code,
                'name' => filled($areaByCode[$code] ?? null)
                    ? $areaByCode[$code].' ('.$code.')'
                    : $code,
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
                'work_area_code' => $request->restrictions['work_area_code'] ?? [],

                'group_company' => $request->restrictions['group_company'] ?? [],

                'contribution_level_code' => $request->restrictions['contribution_level_code'] ?? [],
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
                    'unique:roles,name,'.
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
                'work_area_code' => $request->restrictions['work_area_code'] ?? [],

                'group_company' => $request->restrictions['group_company'] ?? [],

                'contribution_level_code' => $request->restrictions['contribution_level_code'] ?? [],
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

        $selectedIds = collect($validated['users'] ?? [])
            ->map(fn ($id) => (int) $id);

        // Users who currently hold *this* role. The model_has_roles pivot lives
        // on the app (mysql) connection while User lives on kpncorp, so we read
        // membership from the pivot directly rather than through a cross-db
        // relationship.
        $currentIds = DB::connection('mysql')
            ->table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->pluck('model_id')
            ->map(fn ($id) => (int) $id);

        // A user can hold many roles, so this modal only manages membership of
        // the role it was opened for: add it to newly selected users and remove
        // it from deselected ones, leaving each user's other roles untouched.
        // (syncRoles would instead wipe every other role, forcing one role per
        // user.)
        $toAdd = $selectedIds->diff($currentIds);
        $toRemove = $currentIds->diff($selectedIds);

        User::query()
            ->whereIn('id', $toAdd->all())
            ->get()
            ->each(fn ($user) => $user->assignRole($role));

        User::query()
            ->whereIn('id', $toRemove->all())
            ->get()
            ->each(fn ($user) => $user->removeRole($role));

        return back()->with(
            'success',
            'Role assignments updated.'
        );
    }
}
