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
use App\Services\FormImageService;
use App\Services\FormVariableService;
use Barryvdh\DomPDF\Facade\Pdf;
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
                'locale' => $declaration->locale,
                // From the values snapshotted at signing, not today's profile.
                ...$this->forms->renderBilingual($declaration->template, $declaration->variables ?? []),
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
                ...$this->forms->renderBilingual(
                    $applicable,
                    $this->variables->resolve($account, $applicable->period)
                ),
            ],
            'draft' => $draft ? ['agreed_at' => $draft->agreed_at?->toIso8601String()] : null,
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

        return self::pdfResponse($this->forms, $declaration, $request->string('locale')->toString());
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

    private function unavailableMessage(): string
    {
        return request()->input('locale', app()->getLocale()) === 'id'
            ? 'Form ini tidak tersedia atau sudah ditandatangani.'
            : 'This form is not available or has already been signed.';
    }

    private static function mmToPt(float $mm): float
    {
        return round($mm * 72 / 25.4, 2);
    }

    /**
     * Shared with the admin report so both print the same document.
     */
    public static function pdfResponse(
        FormDeclarationService $forms,
        FormDeclaration $declaration,
        string $locale
    ) {
        // A draft has no signature or snapshot to print.
        abort_unless($declaration->isSigned(), 404);

        if (! in_array($locale, ['en', 'id'], true)) {
            $locale = $declaration->locale ?: 'en';
        }

        $document = $forms->renderSigned($declaration, $locale);

        $images = app(FormImageService::class);

        $page = $declaration->template->pageLayout();

        // dompdf cannot follow the session-protected image route, so images
        // are inlined; remote fetching stays off.
        $pdf = Pdf::loadView('pdf.form_declaration', [
            'declaration' => $declaration,
            'title' => $document['title'],
            'content' => $images->inlineForPdf($document['content']),
            'header' => $images->inlineForPdf($document['header']),
            'footer' => $images->inlineForPdf($document['footer']),
            'page' => $page,
            'locale' => $locale,
        ])->setOptions([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
        ])->setPaper([0, 0, self::mmToPt($page['width']), self::mmToPt($page['height'])]);

        $slug = fn (string $value) => trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-');

        $name = $slug((string) data_get($declaration->variables, 'employee_name', 'signer'));

        return $pdf->download(sprintf(
            '%s-%s-%s-%s.pdf',
            $slug($declaration->formType?->name($locale) ?? 'Form'),
            $declaration->period,
            $name,
            strtoupper($locale)
        ));
    }
}
