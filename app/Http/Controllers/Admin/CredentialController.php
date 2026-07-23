<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\CredentialImportErrorExport;
use App\Exports\CredentialTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Mail\NonEmployeeCredentialMail;
use App\Mail\ResetPasswordMail;
use App\Models\CoiDeclaration;
use App\Models\NonEmployee;
use App\Models\NonEmployeeUser;
use App\Models\User;
use App\Imports\NonEmployeeUserImport;
use App\Models\Employee;
use App\Models\Location;
use App\Services\CredentialImportService;
use App\Services\DataScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class CredentialController extends Controller
{
    /**
     * Nationality is stored as a country name ("Indonesia", "Malaysia") to
     * match the country list the form selects from. Anything not explicitly
     * flagged foreigner is Indonesian -- the form only sends the country for
     * foreigners, so it cannot be trusted to carry "Indonesia" itself.
     */
    private function resolveNationality(Request $request): string
    {
        return strtolower((string) $request->nationality_type) === 'foreigner'
            ? (string) $request->nationality
            : 'Indonesia';
    }

    public function index(Request $request, DataScopeService $dataScope): Response
    {
        $search = $request->string('search');

        $viewer = Auth::user();

        $perPage = (int) ($request->per_page ?? 10);

        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 10;
        }

        $sort = in_array(
            $request->sort,
            ['name', 'email', 'business_unit', 'date_of_joining'],
            true
        )
            ? $request->sort
            : 'name';

        $direction = $request->direction === 'desc'
            ? 'desc'
            : 'asc';

        $page = (int) $request->input('page', 1);

        $nonEmployees = NonEmployeeUser::query()
            ->select([
                'id',
                'employee_id',
                'name',
                'email',
            ])
            // Non-employees are users with no HRIS link yet. Once employee_id
            // is set the profile is sourced from kpncorp.employees instead.
            ->whereNull('employee_id')
            // employee.location backs the legacy location_id -> office_area
            // fallback below, so records still on location_id prefill correctly.
            ->with('employee.location')
            ->when(
                $search->isNotEmpty(),
                function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        );
                    });
                }
            )
            // Business unit lives on the related non_employees row, so this
            // filters through the relation rather than a users column.
            ->when(
                $request->filled('business_unit'),
                fn ($query) => $query->whereHas(
                    'employee',
                    fn ($query) => $query->where(
                        'group_company',
                        $request->business_unit
                    )
                )
            )
            // The role's data restrictions live on the same related row. A
            // login with no profile carries none of them, so under a
            // restriction it is not visible either.
            ->when(
                $dataScope->isRestricted($viewer),
                fn ($query) => $query->whereHas(
                    'employee',
                    fn ($query) => $dataScope->applyToPeople($query, $viewer)
                )
            )
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_id' => $user->employee_id,
                'nik' => $user?->employee?->employee_id,
                'phone' => $user?->employee?->personal_mobile_number,
                'type' => 'non_employee',
                'email' => $user->email,
                'citizen_number' => $user?->employee?->ktp,
                'business_unit' => $user?->employee?->group_company,
                // Fall back to the legacy location's area so records still on
                // location_id show their office area on edit; saving then
                // migrates them onto office_area + work_area_code.
                'office_area' => $user?->employee?->office_area
                    ?: $user?->employee?->location?->area,
                'date_of_joining' => $user?->employee?->date_of_joining,
                'address' => $user?->employee?->permanent_address,
                'nationality' => $user?->employee?->nationality,
            ]);
            

        // Rows are already in memory (the profile columns come from a relation),
        // so sorting happens on the collection rather than in SQL.
        $users = $nonEmployees
            ->sortBy(
                fn ($user) => strtolower((string) $user[$sort]),
                SORT_REGULAR,
                $direction === 'desc'
            )
            ->values();

        $paginated = new LengthAwarePaginator(
            // values() matters: forPage() keeps the original keys, so from page
            // two on the slice would serialize as a JSON object instead of an
            // array and the table would render empty.
            $users->forPage($page, $perPage)->values(),
            $users->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $businessUnitOptions = $dataScope
            ->applyToPeople(Employee::query(), $viewer)
            ->whereNotNull('group_company')
            ->distinct()
            ->orderBy('group_company')
            ->pluck('group_company');

        // Office areas (locations.area) offered per business unit. company_name
        // is translated to the app's business unit naming so the frontend can
        // match it against the selected unit directly. The work_area code is
        // resolved from the chosen area at save time.
        $officeAreaOptions = Location::query()
            ->select(['company_name', 'area'])
            ->whereNotNull('area')
            ->where('area', '!=', '')
            ->distinct()
            ->orderBy('area')
            ->get()
            ->map(fn (Location $location) => [
                'business_unit' => Location::businessUnitFor($location->company_name),
                'office_area' => $location->area,
            ])
            ->values();

        return Inertia::render(
            'Admin/Credentials',
            [
                'users' => $paginated,
                'businessUnitOptions' => $businessUnitOptions,
                'officeAreaOptions' => $officeAreaOptions,

                // Only resolved when the convert dialog asks for it by name --
                // there are thousands of employees, far too many to ship with
                // every page load, so the search runs server side.
                'employeeOptions' => Inertia::optional(
                    fn () => $this->employeeOptions(
                        $request->string('employee_search')->toString()
                    )
                ),

                'filters' => [
                    'search' => $request->search,
                    'business_unit' => $request->business_unit,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],
            ]
        );
    }

    /**
     * HRIS employees offered by the convert dialog. Capped and search-driven:
     * the table holds thousands of rows, and an unfiltered list would be
     * unusable as well as slow.
     *
     * Employees already claimed by another local account are excluded, so the
     * dialog cannot offer a link that would then be rejected on submit.
     */
    private function employeeOptions(string $search): array
    {
        if (trim($search) === '') {
            return [];
        }

        $taken = NonEmployeeUser::query()
            ->whereNotNull('employee_id')
            ->pluck('employee_id')
            ->all();

        // Over-fetch, then drop the ones that already declared as an employee.
        // The filtering cannot happen in SQL: coi_declarations lives in the app
        // database and employees in kpncorp, so there is no join to make.
        $matches = Employee::query()
            ->select('id', 'employee_id', 'fullname', 'email', 'group_company', 'designation_name')
            ->whereNull('deleted_at')
            ->whereNotNull('employee_id')
            ->where('employee_id', '!=', '')
            ->when(
                $taken !== [],
                fn ($query) => $query->whereNotIn('employee_id', $taken)
            )
            ->where(
                fn ($query) => $query
                    ->where('employee_id', 'like', "%{$search}%")
                    ->orWhere('fullname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            )
            ->orderBy('fullname')
            ->limit(200)
            ->get();

        $alreadyDeclared = $this->employeeIdsWithDeclarations(
            $matches->pluck('id')->all()
        );

        return $matches
            ->reject(fn (Employee $employee) => in_array((int) $employee->id, $alreadyDeclared, true))
            ->take(25)
            ->map(fn (Employee $employee) => [
                'employee_id' => $employee->employee_id,
                'name' => $employee->fullname,
                'email' => $employee->email,
                'business_unit' => $employee->group_company,
                'designation' => $employee->designation_name,
            ])
            ->values()
            ->all();
    }

    /**
     * Of the given employees, those that already hold declarations of their
     * own. Such an employee is not a conversion target: linking a non-employee
     * to them would pull both declaration histories into one SSO login, so an
     * employee would open their history and find a stranger's records beside
     * their own. It usually means the wrong person was picked from the list.
     *
     * An `employee` declaration keys on employees.id (kpncorp mirrors users.id
     * one-to-one), not on the employee_id string.
     *
     * @param  list<int>  $employeeIds
     * @return list<int>
     */
    private function employeeIdsWithDeclarations(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return CoiDeclaration::query()
            ->where('type', 'employee')
            ->whereIn('user_id', $employeeIds)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Convert a non-employee into an employee.
     *
     * This only writes the link (users.employee_id) -- no declaration, profile
     * or response row is moved, rewritten or deleted. Their existing
     * declarations keep their own user_id and type, and become readable from
     * the SSO account through DeclarationScopeService, which treats the local
     * account as a prior identity of the employee. Reverting is a matter of
     * clearing the column again.
     */
    public function convertToEmployee(
        Request $request,
        NonEmployeeUser $user
    ): RedirectResponse {

        $validated = $request->validate([
            'employee_id' => ['required', 'string', 'max:25'],
        ]);

        $employeeId = trim($validated['employee_id']);

        if (filled($user->employee_id)) {
            return back()->withErrors([
                'employee_id' => 'This user has already been converted to an employee.',
            ]);
        }

        $employee = Employee::query()
            ->whereNull('deleted_at')
            ->where('employee_id', $employeeId)
            ->first();

        if (! $employee) {
            return back()->withErrors([
                'employee_id' => 'That employee could not be found in the HRIS.',
            ]);
        }

        // One HRIS record must not back two local accounts, or a single SSO
        // login would inherit both of their declaration histories.
        $claimedBy = NonEmployeeUser::query()
            ->where('employee_id', $employeeId)
            ->whereKeyNot($user->id)
            ->first();

        if ($claimedBy) {
            return back()->withErrors([
                'employee_id' => "That employee is already linked to {$claimedBy->name}.",
            ]);
        }

        // The SSO login matches on email against kpncorp.users. Without a row
        // there the person cannot sign in as an employee at all, so the link
        // would silently strand them.
        $hasSsoAccount = User::query()
            ->where('id', $employee->id)
            ->orWhere('employee_id', $employeeId)
            ->exists();

        if (! $hasSsoAccount) {
            return back()->withErrors([
                'employee_id' => 'That employee has no SSO account yet, so they could not sign in after converting.',
            ]);
        }

        // The dialog already hides these, so reaching here means the id was
        // posted directly. Refuse it: merging two declaration histories into
        // one login is not undone by clearing the link, because by then the
        // admin has no way to tell which records belonged to whom.
        if ($this->employeeIdsWithDeclarations([(int) $employee->id]) !== []) {
            return back()->withErrors([
                'employee_id' => 'That employee already has their own declaration records, so they cannot be used for a conversion.',
            ]);
        }

        $user->update([
            'employee_id' => $employeeId,
        ]);

        return back()->with(
            'success',
            "{$user->name} is now linked to employee {$employeeId}. Their previous declarations stay available after they sign in with SSO."
        );
    }

    public function store(
        StoreUserRequest $request
        ): RedirectResponse {
            DB::transaction(function () use ($request) {

                $password = Str::password(12);

                $user = NonEmployeeUser::query()->create([
                    'name' => $request->name,
                    'email' => $request->email,
                    // KTP is the only identity stable across promotion: email
                    // moves to the office domain and ids differ per database.
                    // 'citizen_number' => $request->citizen_number,
                    'password' => Hash::make($password),
                ]);

                NonEmployee::query()->create([
                    'user_id' => $user->id,
                    'employee_id' => $request->nik,
                    'fullname' => $request->name,
                    'email' => $request->email,
                    'personal_mobile_number' => $request->phone,
                    'ktp' => $request->citizen_number,
                    'permanent_address' => $request->address,
                    'group_company' => $request->business_unit,
                    'office_area' => $request->office_area,
                    'work_area_code' => Location::workAreaFor(
                        $request->business_unit,
                        $request->office_area,
                    ),
                    'date_of_joining' => $request->date_of_joining,
                    'nationality' => $this->resolveNationality($request),
                ]);

                DB::afterCommit(function () use ($user, $password, $request) {
                    Mail::to($request->email)
                        ->queue(
                            new NonEmployeeCredentialMail(
                                $user,
                                $password,
                            )
                        );
                });
            });

            return back()->with(
                'success',
                'User created successfully.'
            );
        }

    public function update(
        UpdateUserRequest $request,
        NonEmployeeUser $user,
        NonEmployee $employee
    ): RedirectResponse {

        DB::transaction(function () use (
            $request,
            $user,
            $employee,
        ) {

            $update = $user->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            $employee = $user->employee()->firstOrFail();

            $employee->update([
                'employee_id' => $request->nik,

                'fullname' => $request->name,

                'email' => $request->email,

                'personal_mobile_number' => $request->phone,

                'ktp' => $request->citizen_number,

                'permanent_address' => $request->address,

                'group_company' => $request->business_unit,

                'office_area' => $request->office_area,

                'work_area_code' => Location::workAreaFor(
                    $request->business_unit,
                    $request->office_area,
                ),

                'date_of_joining' => $request->date_of_joining,

                'nationality' => $this->resolveNationality($request),
            ]);
        
            });

        return back()->with(
            'success',
            'User updated successfully.'
        );
    }

    public function resetPassword(NonEmployeeUser $user): RedirectResponse
    {
        $password = Str::password(12);

        $user->update([
            'password' => Hash::make($password),

            // Re-open self-service: clearing this makes the user eligible for a
            // fresh magic link from the chatbot API, so a reset still works even
            // when the email never arrives.
            'password_set_at' => null,
        ]);

        Log::info(
            'Password reset for user: ' . $user->email
        );

        Mail::to($user->email)->send(
            new ResetPasswordMail(
                $user,
                $password,
            )
        );

        return back()->with(
            'success',
            'Password has been reset successfully.'
        );
    }

    public function destroy(
        NonEmployeeUser $user
    ): RedirectResponse {

        DB::transaction(function () use (
            $user
        ) {

            $user->employee()
                ->delete();

            $user->delete();
        });

        return back()->with(
            'success',
            'User deleted successfully.'
        );
    }

    public function import(
        Request $request
    ): RedirectResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
            ],
        ]);

        Excel::import(
            new NonEmployeeUserImport(
                app(CredentialImportService::class)
            ),
            $request->file('file')
        );

        return back()->with(
            'success',
            'Users imported successfully.'
        );
    }

    public function downloadImportError()
    {
        $errors = collect(
            session(
                'credential_import_errors',
                []
            )
        );

        abort_if(
            $errors->isEmpty(),
            404
        );

        return Excel::download(

            new CredentialImportErrorExport(
                $errors
            ),

            'Credential Import Errors.xlsx'

        );
    }

    public function downloadTemplate()
    {
        return Excel::download(
            new CredentialTemplateExport(),
            'Commitment-Corner-NonEmployee-User-Import-Template.xlsx'
        );
    }
}