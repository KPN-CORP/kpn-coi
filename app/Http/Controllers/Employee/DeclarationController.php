<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDraftRequest;
use App\Http\Requests\SubmitDeclarationRequest;
use App\Http\Resources\DeclarationResource;
use App\Http\Resources\UserResource;
use App\Models\CoiDeclaration;
use App\Models\NonEmployeeUser;
use App\Models\User;
use App\Services\CoiDeclarationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DeclarationController extends Controller
{
    public function __construct(
        private readonly CoiDeclarationService $service
    ) {}

    public function saveDraft(
        SaveDraftRequest $request
    ): RedirectResponse {
        
        $this->service->saveDraft(
            user: $request->user(),
            responses: $request->validated('responses'),
            period: now()->year,
            type: $this->resolveDeclarationType($request->user()),
        );

        return back()->with(
            'success',
            'Draft saved successfully.'
        );
    }

    public function submit(
    SubmitDeclarationRequest $request
    ): RedirectResponse {
        $this->service->submit(
            user: $request->user(),
            responses: $request->validated('responses'),
            period: now()->year,
            type: $this->resolveDeclarationType($request->user()),
        );

        return redirect()
            ->route('employee.history')
            ->with(
                'success',
                'Declaration submitted successfully.'
            );
    }

    public function create(Request $request): Response
    {
        $authUser = Auth::user();

        $locale = $request->string('locale', 'en');

        $draft = CoiDeclaration::query()
        ->with('responses')
        ->where('user_id', $authUser->id)
        ->where(
            'type',
            $this->resolveDeclarationType($authUser)
        )
        ->where('period', now()->year)
        ->first();

        return Inertia::render(
            'Employee/DeclarationForm',
            [
                'locale' => $locale,

                'draft' => $draft
                    ? new DeclarationResource($draft)
                    : null,

                'declaration' => $this->buildDeclarationData($authUser),
            ]
        );
    }

    private function buildDeclarationData(User|NonEmployeeUser $user): array
    {
        return match (true) {
            $user->employee !== null => [
                'type' => 'employee',
                'name' => $user->employee->fullname,
                'citizen_number' => $user->employee->ktp,
                'address' => $user->employee->current_address,
            ],

            $user->nonEmployee !== null => [
                'type' => 'non_employee',
                'name' => $user->nonEmployee->fullname,
                'citizen_number' => $user->nonEmployee->ktp,
                'address' => $user->nonEmployee->current_address,
            ],

            default => [
                'type' => 'unknown',
                'name' => $user->fullname,
                'citizen_number' => $user->ktp,
                'address' => $user->current_address,
            ],
        };
    }

    private function resolveDeclarationType(User $user): string
    {
        return $user->employee
            ? 'employee'
            : 'non_employee';
    }

}