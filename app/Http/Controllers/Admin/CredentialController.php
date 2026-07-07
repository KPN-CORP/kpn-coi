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
use App\Services\CredentialImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
    public function index(Request $request): Response
    {
        $search = $request->string('search');

        $nonEmployees = NonEmployeeUser::query()
            ->select([
                'id',
                'name',
                'email',
            ])
            ->with('employee')
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
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_id' => null,
                'type' => 'non_employee',
                'email' => $user->email,
                'citizen_number' => $user->employee->ktp,
                'gender' => $user->employee->gender,
                'address' => $user->employee->current_address,
            ]);
            

        $users = $nonEmployees
            ->sortBy('name')
            ->values();

        $paginated = new LengthAwarePaginator(
            $users->forPage(
                request('page', 1),
                10
            ),
            $users->count(),
            10,
            request('page', 1),
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return Inertia::render(
            'Admin/Credentials',
            [
                'users' => $paginated,

                'filters' => [
                    'search' => $request->search,
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
                    'password' => Hash::make($password),
                ]);

                NonEmployee::query()->create([
                    'id' => $user->id,
                    'fullname' => $request->name,
                    'email' => $request->email,
                    'ktp' => $request->citizen_number,
                    'current_address' => $request->address,
                    'gender' => $request->gender,
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

            $user->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            $employee->update([
                    'fullname' => $request->name,

                    'email' => $request->email,

                    'ktp'
                        => $request->citizen_number,

                    'current_address'
                        => $request->address,

                    'gender'
                        => $request->gender,
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