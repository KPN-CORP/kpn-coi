<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDraftRequest;
use App\Http\Requests\SubmitDeclarationRequest;
use App\Http\Resources\DeclarationResource;
use App\Http\Resources\UserResource;
use App\Models\BusinessUnit;
use App\Models\CoiDeclaration;
use App\Models\Companies;
use App\Models\Department;
use App\Models\Employee;
use App\Models\NonEmployeeUser;
use App\Models\User;
use App\Services\CoiDeclarationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DeclarationController extends Controller
{
    protected $authUser;

    public function __construct(private readonly CoiDeclarationService $service) {

    $this->authUser = Auth::guard('web')->user()
            ?? Auth::guard('non_employee')->user();

    }

    public function saveDraft(
        SaveDraftRequest $request
    ): RedirectResponse {
        
        $this->service->saveDraft(
            user: $this->authUser,
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
            responses: $request->input('responses'),
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
        $authUser = Auth::guard('web')->user()
            ?? Auth::guard('non_employee')->user();

        $locale = $request->string('locale', 'en');

        $draft = CoiDeclaration::query()
        ->with('responses')
        ->where('user_id', $authUser->id)
        ->where(
            'type',
            $this->resolveDeclarationType($authUser)
        )
        ->where('status', 'draft')
        ->where('period', now()->year)
        ->first();

        $previousDeclaration = CoiDeclaration::query()
        ->with('responses')
        ->where('user_id', $authUser->id)
        ->where('status', 'submitted')
        ->latest('created_at')
        ->first();

        return Inertia::render(
            'Employee/DeclarationForm',
            [
                'locale' => $locale,

                'draft' => $draft
                    ? new DeclarationResource($draft)
                    : null,

                'declaration' => $this->buildDeclarationData($authUser),

                'previousDeclaration' => $previousDeclaration,

                // 'businessUnits' => Employee::query()
                //     ->select('group_company')
                //     ->whereNotNull('group_company')
                //     ->where('group_company', '!=', '')
                //     ->distinct()
                //     ->orderBy('group_company')
                //     ->get()
                //     ->map(fn ($item) => [
                //         'code' => $item->group_company,
                //         'name' => $item->group_company,
                //     ])
                //     ->values(),

                
                // 'companies' => Employee::query()
                //     ->select('group_company', 'company_name', 'contribution_level_code')
                //     ->whereNotNull('group_company')
                //     ->whereNotNull('company_name')
                //     ->where('group_company', '!=', '')
                //     ->where('company_name', '!=', '')
                //     ->distinct()
                //     ->orderBy('contribution_level_code')
                //     ->get()
                //     ->map(fn ($item) => [
                //         'business_unit' => $item->group_company,
                //         'code' => $item->contribution_level_code,
                //         'name' => $item->company_name,
                //     ])
                //     ->values(),

                'businessUnits' => BusinessUnit::query()
                    ->select('nama_bisnis')
                    ->whereNot('nama_bisnis', 'others')
                    ->get()
                    ->map(fn ($item) => [
                        'code' => $item->nama_bisnis,
                        'name' => $item->nama_bisnis
                    ])->values(),
                
                'companies' => Companies::query()
                    ->select('company_name', 'contribution_level', 'contribution_level_code')
                    ->get()
                    ->map(fn ($item) => [
                        'business_unit' => explode(',', $item->company_name)[1],
                        'code' => $item->contribution_level_code,
                        'name' => $item->contribution_level,
                    ])->values(),

                'departments' => Employee::query()
                    ->select('group_company', 'unit')
                    ->whereNotNull('group_company')
                    ->whereNotNull('unit')
                    ->where('group_company', '!=', '')
                    ->where('unit', '!=', '')
                    ->distinct()
                    ->orderBy('unit')
                    ->get()
                    ->map(fn ($item) => [
                        'business_unit' => $item->group_company,
                        'code' => $item->unit,
                        'name' => $item->unit,
                    ])
                    ->values(),
            ]
        );
    }

    public function downloadPdf(Request $request, CoiDeclaration $declaration)
    {
        $authUser = Auth::guard('web')->user()
            ?? Auth::guard('non_employee')->user();

        $locale = $request->string('locale', 'en');

        abort_unless(
            $authUser && $declaration->user_id === $authUser->id,
            403
        );

        $declaration->load([
            'responses',
            'user.employee',
        ]);

        $locale = request()->string('locale')->toString();

        if (! in_array($locale, ['en', 'id'])) {
            $locale = 'en';
        }

        $pdf = Pdf::loadView(
            'pdf.declaration',
            [
                'declaration' => $declaration,
                'locale' => $locale,
            ]
        )->setOptions([
            'isPhpEnabled' => true,
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);

        return $pdf->download(
            sprintf(
                'COI-Declaration-%s-%s.pdf',
                $declaration->period,
                $declaration->created_at->format('Ymd_His')
            )
        );
    }

    private function buildDeclarationData(User|NonEmployeeUser $user): array
    {
        return match (true) {
            $user->employee !== null => [
                'type' => 'employee',
                'name' => $user->employee->fullname,
                'citizen_number' => $user->employee->ktp,
                'address' => $user->employee->permanent_address,
            ],

            $user->nonEmployee !== null => [
                'type' => 'non_employee',
                'name' => $user->nonEmployee->fullname,
                'citizen_number' => $user->nonEmployee->ktp,
                'address' => $user->nonEmployee->permanent_address,
            ],

            default => [
                'type' => 'unknown',
                'name' => $user->fullname,
                'citizen_number' => $user->ktp,
                'address' => $user->permanent_address,
            ],
        };
    }

    private function resolveDeclarationType(
        User|NonEmployeeUser $user
    ): string {
        return $user instanceof User
            ? 'employee'
            : 'non_employee';
    }

}