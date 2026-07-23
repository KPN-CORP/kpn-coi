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
            'Compliance-NonEmployee-User-Import-Template.xlsx'
        );
    }
}