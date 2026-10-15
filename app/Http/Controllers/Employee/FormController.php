<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\SignFormRequest;
use App\Models\FormDeclaration;
use App\Models\FormTemplate;
use App\Models\FormType;
use App\Models\NonEmployeeUser;
use App\Models\User;
use App\Services\DeclarationScopeService;
use App\Services\FormDeclarationService;
use App\Services\FormPdfService;
use App\Services\FormVariableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormController extends Controller
{
    public function __construct(
        private readonly FormDeclarationService $forms,
        private readonly FormVariableService $variables,
        private readonly DeclarationScopeService $scope,
        private readonly FormPdfService $pdf,
    ) {}

    /**
     * One page per form type: what is waiting to be signed (with any saved
     * draft), and what was signed. The documents themselves are read on
     * read() -- this page only lists them.
     */
    public function index(Request $request, FormType $formType): Response
    {
        abort_unless($formType->is_active, 404);

        $account = $request->user();

        $drafts = $this->forms->ownDeclarations($account)
            ->draft()
            ->where('form_type_id', $formType->id)
            ->get()
            ->keyBy('period');

        $pending = $this->forms->pendingFor($account, $formType->id)
            ->map(fn (FormTemplate $template) => [
                'id' => $template->id,
                'period' => $template->period,
                'publish_at' => $template->publish_at?->toIso8601String(),
                'title' => ['en' => $template->title_en, 'id' => $template->title_id],
                'draft_saved_at' => $drafts->get($template->period)?->agreed_at?->toIso8601String(),
                // When the draft row was first saved; nothing yet without one.
                'created_at' => $drafts->get($template->period)?->created_at?->toIso8601String(),
            ])
            ->values();

        // History follows every identity this person has held (rehires,
        // non-employee -> employee), like the COI history does.
        $signed = $this->scope
            ->applyOwnership(FormDeclaration::query()->signed()->with('template'), $account)
            ->where('form_type_id', $formType->id)
            ->orderByDesc('period')
            ->orderByDesc('signed_at')
            ->get()
            ->map(fn (FormDeclaration $declaration) => [
                'id' => $declaration->id,
                'period' => $declaration->period,
                'signed_name' => $declaration->signed_name,
                'signed_at' => $declaration->signed_at?->toIso8601String(),
                // The row's first save: the draft, or the signature itself.
                'created_at' => $declaration->created_at?->toIso8601String(),
                'locale' => $declaration->locale,
                // From the values snapshotted at signing, not today's profile.
                ...$this->forms->renderBilingual($declaration->template, $this->forms->signedValues($declaration)),
            ])
            ->values();

        return Inertia::render('Employee/Form', [
            'formType' => $formType->summary(),
            'pending' => $pending,
            'signed' => $signed,
        ]);
    }

    /**
     * Read one pending document before agreeing/signing. Rendered with today's
     * data -- exactly what the signer will be agreeing to if they sign now.
     */
    public function read(Request $request, FormType $formType, FormTemplate $template): Response|RedirectResponse
    {
        $account = $request->user();

        $applicable = $this->applicable($account, $template);

        if (! $applicable || $applicable->form_type_id !== $formType->id) {
            return redirect()
                ->route('employee.forms.show', $formType)
                ->with('error', $this->unavailableMessage());
        }

        $draft = $this->forms->draftFor($account, $applicable);

        return Inertia::render('Employee/FormRead', [
            'formType' => $formType->summary(),
            'document' => [
                'id' => $applicable->id,
                'period' => $applicable->period,
                'publish_at' => $applicable->publish_at?->toIso8601String(),
                // The route-bound model carries the full body; the pending
                // lookup only loads the columns it needs to decide.
                ...$this->forms->renderBilingual(
                    $template,
                    $this->variables->resolve($account, $template->period)
                ),
            ],
            'draft' => $draft ? ['agreed_at' => $draft->agreed_at?->toIso8601String()] : null,
            'version' => self::version($template),
            'fullName' => $this->variables->fullNameFor($account),
        ]);
    }

    /**
     * "I have read and agree" saved for later; signing is a separate step.
     */
    public function draft(Request $request, FormTemplate $template): RedirectResponse
    {
        $request->validate([
            'locale' => ['required', 'in:en,id'],
            'agree' => ['accepted'],
        ]);

        $applicable = $this->applicable($request->user(), $template);

        if (! $applicable) {
            return back()->with('error', $this->unavailableMessage());
        }

        $this->forms->saveDraft($request->user(), $applicable, (string) $request->input('locale'));

        return back()->with(
            'success',
            $request->input('locale') === 'id'
                ? 'Draf disimpan. Anda dapat kembali dan menandatanganinya kapan saja.'
                : 'Draft saved. You can come back and sign it any time.'
        );
    }

    public function sign(SignFormRequest $request, FormTemplate $template): RedirectResponse
    {
        $account = $request->user();

        $applicable = $this->applicable($account, $template);

        if (! $applicable) {
            return back()->with('error', $this->unavailableMessage());
        }

        // The signer agrees to the wording they read. If an admin changed the
        // template after the page was opened, send them back to re-read it.
        if ($request->input('version') !== self::version($template)) {
            return redirect()
                ->route('employee.forms.read', [$applicable->formType, $template])
                ->with('error', $request->input('locale') === 'id'
                    ? 'Dokumen ini baru saja diperbarui. Silakan baca kembali sebelum menandatangani.'
                    : 'This document was just updated. Please read it again before signing.');
        }

        $this->forms->sign(
            $account,
            $applicable,
            (string) $request->input('signed_name'),
            (string) $request->input('locale'),
            $request
        );

        $formType = $applicable->formType;

        return redirect()
            ->route('employee.forms.show', $formType)
            ->with(
                'success',
                $request->input('locale') === 'id'
                    ? "{$formType->name_id} berhasil ditandatangani."
                    : "{$formType->name_en} signed successfully."
            );
    }

    public function pdf(Request $request, FormDeclaration $declaration)
    {
        abort_unless($this->scope->owns($request->user(), $declaration), 403);

        return $this->pdf->download($declaration, $request->string('locale')->toString());
    }

    /**
     * The template, if it is live, meant for this person, and not yet signed --
     * the id comes from the page, so it is re-resolved rather than trusted.
     */
    private function applicable(User|NonEmployeeUser $account, FormTemplate $template): ?FormTemplate
    {
        return $this->forms->pendingFor($account, $template->form_type_id)
            ->firstWhere('id', $template->id);
    }

    /**
     * Identifies the wording a signer read (see sign()).
     */
    private static function version(FormTemplate $template): string
    {
        return (string) $template->updated_at?->getTimestamp();
    }

    private function unavailableMessage(): string
    {
        return request()->input('locale', app()->getLocale()) === 'id'
            ? 'Form ini tidak tersedia atau sudah ditandatangani.'
            : 'This form is not available or has already been signed.';
    }
}
