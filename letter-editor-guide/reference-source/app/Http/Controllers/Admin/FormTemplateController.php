<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveFormTemplateRequest;
use App\Models\FormTemplate;
use App\Models\FormType;
use App\Models\User;
use App\Services\FormTemplateService;
use App\Services\FormVariableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FormTemplateController extends Controller
{
    public function __construct(
        private readonly FormTemplateService $templates,
        private readonly FormVariableService $variables,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $perPage = (int) $request->input('per_page', 10);

        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 10;
        }

        $paginated = $this->templates
            ->visibleTo(FormTemplate::query(), $user)
            ->with('formType')
            ->withCount('signedDeclarations')
            ->when(
                $request->filled('form_type'),
                fn ($query) => $query->where('form_type_id', (int) $request->form_type)
            )
            ->when(
                $request->filled('period'),
                fn ($query) => $query->where('period', (int) $request->period)
            )
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(
                    fn ($inner) => $inner
                        ->where('title_en', 'like', '%'.$request->search.'%')
                        ->orWhere('title_id', 'like', '%'.$request->search.'%')
                )
            )
            ->orderByDesc('period')
            ->orderByDesc('publish_at')
            ->paginate($perPage)
            ->withQueryString();

        $paginated->getCollection()->transform(
            fn (FormTemplate $template) => [
                ...$this->summary($template),
                'can_manage' => $this->templates->canManage($template, $user),
            ]
        );

        return Inertia::render('Admin/Forms/Templates', [
            'templates' => $paginated,
            'filters' => $request->only(['form_type', 'period', 'search']),
            'formTypes' => FormType::options(),
            'periods' => FormTemplate::query()
                ->distinct()
                ->orderByDesc('period')
                ->pluck('period'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, FormTemplate $template): Response
    {
        abort_unless(
            $this->templates
                ->visibleTo(FormTemplate::query(), $request->user())
                ->whereKey($template->id)
                ->exists(),
            403
        );

        return $this->form($request, $template);
    }

    public function store(SaveFormTemplateRequest $request): RedirectResponse
    {
        $this->templates->save(null, $request->validated(), $request->user());

        return redirect()
            ->route('admin.master.templates.index')
            ->with('success', 'Template created successfully.');
    }

    public function update(SaveFormTemplateRequest $request, FormTemplate $template): RedirectResponse
    {
        abort_unless($this->templates->canManage($template, $request->user()), 403);

        // Checked and written under the template's row lock -- the same lock
        // signing takes -- so a signature cannot land in between.
        $saved = DB::transaction(function () use ($request, $template) {
            $current = FormTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            if ($current->isLocked()) {
                return false;
            }

            $this->templates->save($current, $request->validated(), $request->user());

            return true;
        });

        if (! $saved) {
            return back()->with(
                'error',
                'This template has already been signed and can no longer be edited. Duplicate it to make changes.'
            );
        }

        return redirect()
            ->route('admin.master.templates.index')
            ->with('success', 'Template updated successfully.');
    }

    /**
     * Switch a template on or off. Allowed on signed (locked) templates too --
     * it only stops new signatures; the signed ones are untouched.
     */
    public function toggle(Request $request, FormTemplate $template): RedirectResponse
    {
        abort_unless($this->templates->canManage($template, $request->user()), 403);

        if (! $template->is_active) {
            $conflicts = $this->templates->conflicts(
                $template->form_type_id,
                $template->period,
                $template->business_units ?? [],
                $template->id
            );

            if ($conflicts->isNotEmpty()) {
                return back()->with(
                    'error',
                    'Another active template already covers the same business unit for this period: '
                        .$conflicts->pluck('title_en')->implode(', ').'.'
                );
            }
        }

        $template->update([
            'is_active' => ! $template->is_active,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with(
            'success',
            $template->is_active ? 'Template activated.' : 'Template deactivated.'
        );
    }

    /**
     * Copy a template into a new, inactive draft -- the way to change the
     * wording of a template that has already been signed.
     */
    public function duplicate(Request $request, FormTemplate $template): RedirectResponse
    {
        abort_unless($this->templates->canManage($template, $request->user()), 403);

        $copy = $template->replicate(['created_by', 'updated_by']);
        $copy->is_active = false;
        $copy->title_en = mb_substr($template->title_en.' (Copy)', 0, 255);
        $copy->title_id = mb_substr($template->title_id.' (Salinan)', 0, 255);
        $copy->created_by = $request->user()->id;
        $copy->updated_by = $request->user()->id;
        $copy->save();

        return redirect()
            ->route('admin.master.templates.edit', $copy)
            ->with('success', 'Template duplicated as an inactive draft.');
    }

    public function destroy(Request $request, FormTemplate $template): RedirectResponse
    {
        abort_unless($this->templates->canManage($template, $request->user()), 403);

        // Same row lock as update() and signing.
        $deleted = DB::transaction(function () use ($template) {
            $current = FormTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();

            return $current->isLocked() ? false : (bool) $current->delete();
        });

        if (! $deleted) {
            return back()->with(
                'error',
                'This template has already been signed and cannot be deleted. Deactivate it instead.'
            );
        }

        return back()->with('success', 'Template deleted.');
    }

    private function form(Request $request, ?FormTemplate $template): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Forms/TemplateForm', [
            'template' => $template ? [
                ...$this->summary($template),
                'content_en' => $template->content_en,
                'content_id' => $template->content_id,
                'header_en' => (string) $template->header_en,
                'header_id' => (string) $template->header_id,
                'footer_en' => (string) $template->footer_en,
                'footer_id' => (string) $template->footer_id,
                ...$template->pageLayout(),
                'can_manage' => $this->templates->canManage($template, $user),
            ] : null,
            'paperSizes' => collect(FormTemplate::PAPER_SIZES)
                ->map(fn (array $size, string $key) => ['key' => $key, 'width' => $size[0], 'height' => $size[1]])
                ->values(),
            'businessUnitOptions' => $this->templates->allowedUnits($user),
            'unitRestricted' => $this->templates->isUnitRestricted($user),
            'variables' => collect($this->variables->definitions())
                ->map(fn (array $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
            // Starting points for the editor's "Use sample" picker.
            'samples' => config('form_template_samples', []),
            'fontOptions' => [
                'fonts' => collect(config('forms.fonts'))
                    ->map(fn (array $font, string $key) => ['key' => $key, ...$font])
                    ->values(),
                'sizes' => config('forms.font_sizes'),
                'lineHeights' => config('forms.line_heights'),
                'textColors' => config('forms.text_colors'),
                'highlightColors' => config('forms.highlight_colors'),
            ],
            'formTypes' => FormType::options(),
            // Preselect the type the list was filtered by when creating.
            'defaultFormType' => FormType::selectedOrDefault($request->input('form_type'))?->id,
            // The admin's own data, already formatted per language by the
            // same code signers get, so the preview reads like a real signed
            // form -- signature included.
            'previewValues' => $this->previewValues($user, (int) ($template?->period ?? now()->year)),
        ]);
    }

    /**
     * @return array{en: array<string, string>, id: array<string, string>}
     */
    private function previewValues(User $user, int $period): array
    {
        $values = [
            ...$this->variables->resolve($user, $period),
            'signed_name' => $this->variables->fullNameFor($user),
        ];

        return [
            'en' => $this->variables->displayValues($values, 'en'),
            'id' => $this->variables->displayValues($values, 'id'),
        ];
    }

    private function summary(FormTemplate $template): array
    {
        $signedCount = $template->signed_declarations_count
            ?? $template->signedDeclarations()->count();

        return [
            'id' => $template->id,
            'form_type_id' => $template->form_type_id,
            'form_type' => $template->formType
                ? ['en' => $template->formType->name_en, 'id' => $template->formType->name_id]
                : null,
            'period' => $template->period,
            'title_en' => $template->title_en,
            'title_id' => $template->title_id,
            'business_units' => $template->business_units ?? [],
            'publish_at' => $template->publish_at?->format('Y-m-d\TH:i'),
            'is_active' => $template->is_active,
            'is_published' => $template->publish_at !== null && $template->publish_at->isPast(),
            'signed_count' => $signedCount,
            'locked' => $signedCount > 0,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
