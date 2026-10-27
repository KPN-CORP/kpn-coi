<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Enums\DeclarationStatus;
use App\Http\Controllers\Controller;
use App\Models\CoiDeclaration;
use App\Models\FormTemplate;
use App\Models\FormType;
use App\Services\DeclarationScopeService;
use App\Services\FormDeclarationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My Commitment": one list of everything this person still has to do --
 * this year's COI declaration and every form waiting for their signature,
 * across all active form types.
 */
class CommitmentController extends Controller
{
    public function __construct(
        private readonly DeclarationScopeService $scope,
        private readonly FormDeclarationService $forms,
    ) {}

    public function __invoke(Request $request): Response
    {
        $account = $request->user();

        $period = now()->year;

        // Same ownership rule as the COI history: every identity this person
        // has held, matched on (user_id, type).
        $coiRows = $this->scope
            ->applyOwnership(CoiDeclaration::query(), $account)
            ->where('period', $period)
            ->get(['id', 'status', 'submitted_at', 'updated_at']);

        $submitted = $coiRows
            ->filter(fn (CoiDeclaration $row) => $row->status !== DeclarationStatus::Draft)
            ->sortByDesc('submitted_at')
            ->first();

        $draft = $coiRows
            ->filter(fn (CoiDeclaration $row) => $row->status === DeclarationStatus::Draft)
            ->sortByDesc('updated_at')
            ->first();

        // Shown under the name whatever this year's status: the latest
        // submission of any period.
        $coiLastSubmitted = $this->scope
            ->applyOwnership(CoiDeclaration::query(), $account)
            ->where('status', '!=', DeclarationStatus::Draft)
            ->max('submitted_at');

        $types = FormType::query()->active()->ordered()->get()->keyBy('id');

        // Same for forms, per form type.
        $lastSignedByType = $this->forms->ownDeclarations($account)
            ->signed()
            ->whereIn('form_type_id', $types->keys())
            ->groupBy('form_type_id')
            ->selectRaw('form_type_id, MAX(signed_at) AS last_signed_at')
            ->pluck('last_signed_at', 'form_type_id');

        $lastSigned = fn (int $typeId): ?string => isset($lastSignedByType[$typeId])
            ? Carbon::parse($lastSignedByType[$typeId])->toIso8601String()
            : null;

        $drafts = $this->forms->ownDeclarations($account)
            ->draft()
            ->get(['id', 'template_id', 'agreed_at'])
            ->keyBy('template_id');

        $pendingByType = $this->forms->pendingFor($account)->groupBy('form_type_id');

        // A form type with nothing left to sign keeps a row while this year
        // has a signature on it, so the person can still reach its history
        // (like the COI row once submitted).
        $signedByType = $this->forms->ownDeclarations($account)
            ->signed()
            ->whereIn('form_type_id', $types->keys())
            ->where('period_year', $period)
            ->with(['template' => fn ($query) => $query->select(['id', 'title_en', 'title_id'])])
            ->orderByDesc('signed_at')
            ->get(['id', 'form_type_id', 'template_id', 'period_year', 'period_month'])
            ->unique('form_type_id')
            ->keyBy('form_type_id');

        // In menu order, newest period first within a type.
        $forms = $types
            ->flatMap(function (FormType $type) use ($account, $pendingByType, $signedByType, $drafts, $lastSigned) {
                $pending = $pendingByType->get($type->id) ?? collect();

                if ($pending->isEmpty()) {
                    $signed = $signedByType->get($type->id);

                    return $signed ? [[
                        'id' => $signed->template_id,
                        'period' => $signed->period_year,
                        'title' => ['en' => $signed->template?->title_en, 'id' => $signed->template?->title_id],
                        'form_type' => $type->summary(),
                        'status' => 'signed',
                        'last_submitted_at' => $lastSigned($type->id),
                        'draft_saved_at' => null,
                        'resign' => false,
                    ]] : [];
                }

                return $pending
                    ->sortByDesc('period')
                    ->map(fn (FormTemplate $template) => [
                        'id' => $template->id,
                        // Forms are shown by year here, like the COI row.
                        'period' => $template->period_year,
                        'title' => ['en' => $template->title_en, 'id' => $template->title_id],
                        'form_type' => $type->summary(),
                        'status' => $drafts->has($template->id) ? 'draft' : 'pending',
                        'last_submitted_at' => $lastSigned($type->id),
                        'draft_saved_at' => $drafts->get($template->id)?->agreed_at?->toIso8601String(),
                        'resign' => $this->forms->mustSignAgain($account, $template),
                    ]);
            })
            ->values();

        return Inertia::render('Employee/Commitment', [
            'coi' => [
                'period' => $period,
                'status' => $submitted ? 'done' : ($draft ? 'draft' : 'pending'),
                'submitted_at' => $submitted?->submitted_at?->toIso8601String(),
                'draft_saved_at' => $draft?->updated_at?->toIso8601String(),
                'last_submitted_at' => $coiLastSubmitted ? Carbon::parse($coiLastSubmitted)->toIso8601String() : null,
            ],
            'forms' => $forms,
        ]);
    }
}
