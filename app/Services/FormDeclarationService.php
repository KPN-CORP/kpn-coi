<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormDeclaration;
use App\Models\FormTemplate;
use App\Models\NonEmployeeUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The signer's side of forms: what is waiting to be signed, and signing it.
 *
 * Each period (a month, App\Support\FormPeriod) a person must sign the form
 * once; after that they may sign again at any time ("Add / Update"). Every
 * signature is its own row and the person's *newest* one is their valid
 * declaration -- the older ones are kept as *superseded* (isSuperseded()).
 *
 * A signed template is locked, so changing its wording means duplicating it
 * and switching the copy on instead: signatures on the old version are then
 * superseded too and their signers are asked to sign again.
 */
class FormDeclarationService
{
    public function __construct(
        private readonly FormTemplateService $templates,
        private readonly FormVariableService $variables,
    ) {}

    public static function typeOf(User|NonEmployeeUser $account): string
    {
        return $account instanceof User
            ? FormDeclaration::TYPE_EMPLOYEE
            : FormDeclaration::TYPE_NON_EMPLOYEE;
    }

    /**
     * Declarations made under this exact account. "Already signed?" is asked
     * of the current identity only, matching how the admin report counts.
     */
    public function ownDeclarations(User|NonEmployeeUser $account): Builder
    {
        return FormDeclaration::query()
            ->where('user_id', $account->id)
            ->where('type', self::typeOf($account));
    }

    /**
     * Templates this account should sign and has not yet: one per form type per
     * period (a saved draft still counts as waiting). Pass a form type id to
     * narrow to that type.
     *
     * A signature only covers the template that applies now, or a template
     * that is still switched on: one on a template replaced by a newer
     * version leaves the new version pending (see isSuperseded()).
     *
     * @return Collection<int, FormTemplate>
     */
    public function pendingFor(User|NonEmployeeUser $account, ?int $formTypeId = null): Collection
    {
        $signed = $this->ownDeclarations($account)
            ->signed()
            ->when($formTypeId, fn (Builder $query) => $query->where('form_type_id', $formTypeId))
            ->with(['template' => fn ($query) => $query->select(['id', 'is_active', 'deleted_at'])])
            ->get(['id', 'form_type_id', 'period', 'template_id'])
            ->groupBy(fn (FormDeclaration $declaration) => $declaration->form_type_id.'-'.$declaration->period);

        return $this->templates
            ->applicableAcrossPeriods($this->variables->businessUnitFor($account), $formTypeId)
            ->reject(fn (FormTemplate $template) => ($signed->get($template->form_type_id.'-'.$template->period) ?? collect())
                ->contains(fn (FormDeclaration $declaration) => ! self::replacedBy($declaration, $template)))
            ->values();
    }

    /**
     * Whether $applicable replaces the declaration's template: a different
     * template applies now, and the signed one has been switched off.
     */
    private static function replacedBy(FormDeclaration $declaration, ?FormTemplate $applicable): bool
    {
        $template = $declaration->template;

        $stillOn = $template && $template->is_active && ! $template->trashed();

        return $applicable !== null && $applicable->id !== $declaration->template_id && ! $stillOn;
    }

    /**
     * Ids of the given (signed) declarations that are superseded -- not the
     * signer's valid declaration for that form type and period, which is
     * their newest signature on a template that has not been replaced.
     *
     * A template counts as replaced when it was switched off and another
     * template now applies to the signer (by the business unit they signed
     * under). Switching one off with nothing replacing it (e.g. at the end
     * of a period) leaves its signatures valid.
     *
     * @param  iterable<FormDeclaration>  $declarations
     * @return array<int, true> keyed by declaration id
     */
    public function supersededIds(iterable $declarations): array
    {
        $declarations = collect($declarations)->filter(fn (FormDeclaration $declaration) => $declaration->isSigned());

        if ($declarations->isEmpty()) {
            return [];
        }

        $group = fn (FormDeclaration $declaration) => implode('-', [
            $declaration->user_id, $declaration->type, $declaration->form_type_id, $declaration->period,
        ]);

        // Every signature of the same signers, forms and periods, newest first.
        $signatures = FormDeclaration::query()
            ->signed()
            ->with(['template' => fn ($query) => $query->select(['id', 'is_active', 'deleted_at'])])
            ->where(function (Builder $query) use ($declarations, $group) {
                foreach ($declarations->unique($group) as $declaration) {
                    $query->orWhere(fn (Builder $one) => $one
                        ->where('user_id', $declaration->user_id)
                        ->where('type', $declaration->type)
                        ->where('form_type_id', $declaration->form_type_id)
                        ->where('period', $declaration->period));
                }
            })
            ->orderByDesc('signed_at')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'type', 'form_type_id', 'period', 'template_id', 'business_unit', 'status', 'signed_at'])
            ->groupBy($group);

        $available = [];

        $replaced = function (FormDeclaration $declaration) use (&$available): bool {
            $template = $declaration->template;

            // Still switched on: never replaced, whatever else applies.
            if ($template && $template->is_active && ! $template->trashed()) {
                return false;
            }

            $key = $declaration->form_type_id.'-'.$declaration->period;

            $available[$key] ??= FormTemplate::query()
                ->select(FormTemplate::SUMMARY_COLUMNS)
                ->available()
                ->where('form_type_id', $declaration->form_type_id)
                ->where('period', $declaration->period)
                ->get();

            return self::replacedBy($declaration, $this->templates->resolve($available[$key], $declaration->business_unit));
        };

        $current = $signatures->map(
            fn (Collection $rows) => $rows->first(fn (FormDeclaration $row) => ! $replaced($row))?->id
        );

        return $declarations
            ->reject(fn (FormDeclaration $declaration) => $current->get($group($declaration)) === $declaration->id)
            ->mapWithKeys(fn (FormDeclaration $declaration) => [$declaration->id => true])
            ->all();
    }

    public function isSuperseded(FormDeclaration $declaration): bool
    {
        return isset($this->supersededIds([$declaration])[$declaration->id]);
    }

    /**
     * Pending count per form type id, for the menu badges.
     *
     * @return array<int, int>
     */
    public function pendingCounts(User|NonEmployeeUser $account): array
    {
        return $this->pendingFor($account)
            ->countBy('form_type_id')
            ->all();
    }

    /**
     * Whitespace runs collapse and the ends are trimmed on both sides -- some
     * HRIS names carry stray double spaces -- but case must match, the same
     * rule the COI submit confirmation uses.
     */
    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    public function nameMatches(User|NonEmployeeUser $account, string $typed): bool
    {
        return self::normalizeName($typed) === self::normalizeName(
            $this->variables->fullNameFor($account)
        );
    }

    /**
     * This account's draft of a template, if any.
     */
    public function draftFor(User|NonEmployeeUser $account, FormTemplate $template): ?FormDeclaration
    {
        return $this->ownDeclarations($account)
            ->draft()
            ->where('template_id', $template->id)
            ->first();
    }

    /**
     * Whether this account signed an earlier version of the form for this
     * template's period -- it is pending again because it was replaced.
     */
    public function mustSignAgain(User|NonEmployeeUser $account, FormTemplate $template): bool
    {
        return $this->ownDeclarations($account)
            ->signed()
            ->where('form_type_id', $template->form_type_id)
            ->where('period', $template->period)
            ->where('template_id', '!=', $template->id)
            ->exists();
    }

    /**
     * The template this account may read and sign for $template's form type
     * and period -- the one that applies to them now -- whether or not they
     * signed it already: signing again adds an updated declaration.
     */
    public function signable(User|NonEmployeeUser $account, FormTemplate $template): ?FormTemplate
    {
        return $this->templates
            ->applicableAcrossPeriods($this->variables->businessUnitFor($account), $template->form_type_id)
            ->firstWhere('id', $template->id);
    }

    /**
     * What "Add / Update" opens: the template that applies to this account in
     * the latest period that has one.
     */
    public function updateTarget(User|NonEmployeeUser $account, int $formTypeId): ?FormTemplate
    {
        return $this->templates
            ->applicableAcrossPeriods($this->variables->businessUnitFor($account), $formTypeId)
            ->sortByDesc('period')
            ->first();
    }

    /**
     * Whether this account already holds a valid signature for the template's
     * period -- signing now adds an update rather than the first declaration.
     */
    public function hasSigned(User|NonEmployeeUser $account, FormTemplate $template): bool
    {
        return ! $this->pendingFor($account, $template->form_type_id)->contains('id', $template->id);
    }

    /**
     * "I have read and agree" without signing yet. One draft per template per
     * account; signing turns it into the signature.
     */
    public function saveDraft(User|NonEmployeeUser $account, FormTemplate $template, string $locale): FormDeclaration
    {
        return $this->locked($template, function () use ($account, $template, $locale) {
            $draft = $this->draftFor($account, $template) ?? $this->newRow($account, $template);

            $draft->fill([
                'status' => FormDeclaration::STATUS_DRAFT,
                'locale' => $locale,
                'agreed_at' => now(),
                'business_unit' => $this->variables->businessUnitFor($account),
            ])->save();

            return $draft;
        });
    }

    /**
     * A new signature -- the person's first for the period, or an update.
     * Every call adds a row (turning the draft into it, if there is one);
     * the newest is the valid declaration.
     */
    public function sign(
        User|NonEmployeeUser $account,
        FormTemplate $template,
        string $signedName,
        string $locale,
        Request $request
    ): FormDeclaration {
        return $this->locked($template, function () use ($account, $template, $signedName, $locale, $request) {
            // A double click or retry of the same submit: keep the signature
            // just made instead of adding an identical one.
            $justSigned = $this->ownDeclarations($account)
                ->signed()
                ->where('template_id', $template->id)
                ->where('signed_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
                ->latest('signed_at')
                ->first();

            if ($justSigned) {
                return $justSigned;
            }

            $signedAt = now();

            $declaration = $this->draftFor($account, $template) ?? $this->newRow($account, $template);

            $declaration->fill([
                'status' => FormDeclaration::STATUS_SIGNED,
                'business_unit' => $this->variables->businessUnitFor($account),
                'locale' => $locale,
                'agreed_at' => $declaration->agreed_at ?? $signedAt,
                'signed_name' => self::normalizeName($signedName),
                'variables' => $this->variables->resolve($account, $template->period, $signedAt),
                'signed_at' => $signedAt,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ])->save();

            return $declaration;
        });
    }

    /** Two submits of one template this close together are the same submit. */
    private const DUPLICATE_WINDOW_SECONDS = 10;

    /**
     * Run a change for a template with its row locked: signing and drafting
     * are serialised per template, and an admin edit/delete of the wording
     * (FormTemplateController, which takes the same lock) cannot slip in
     * between "still unsigned, editable" and a signature.
     *
     * @param  callable(): FormDeclaration  $change
     */
    private function locked(FormTemplate $template, callable $change): FormDeclaration
    {
        return DB::transaction(function () use ($template, $change) {
            FormTemplate::query()->whereKey($template->id)->lockForUpdate()->first();

            return $change();
        });
    }

    private function newRow(User|NonEmployeeUser $account, FormTemplate $template): FormDeclaration
    {
        return new FormDeclaration([
            'template_id' => $template->id,
            'form_type_id' => $template->form_type_id,
            'period_year' => $template->period_year,
            'period_month' => $template->period_month,
            'user_id' => $account->id,
            'type' => self::typeOf($account),
        ]);
    }

    /**
     * The signed document in one language, rendered from the snapshot taken at
     * signing rather than today's profile data.
     *
     * @return array{title: string, content: string}
     */
    public function renderSigned(FormDeclaration $declaration, string $locale): array
    {
        return $this->render($declaration->template, $this->signedValues($declaration), $locale);
    }

    /**
     * The snapshot plus what {{signature}} and {{sign_date}} print. The
     * signing time comes from signed_at, not the snapshot: early snapshots
     * kept the date only.
     *
     * @return array<string, string|null>
     */
    public function signedValues(FormDeclaration $declaration): array
    {
        return [
            ...($declaration->variables ?? []),
            'signed_name' => $declaration->signed_name,
            'sign_date' => $declaration->signed_at?->toIso8601String(),
        ];
    }

    /**
     * One language of a template with the given values filled in -- title,
     * body, header and footer all come in that language.
     *
     * @param  array<string, string|null>  $values
     * @return array{title: string, content: string, header: string, footer: string}
     */
    public function render(FormTemplate $template, array $values, string $locale): array
    {
        $render = fn (string $html) => self::keepBlankLines(
            $this->variables->render($html, $values, $locale)
        );

        return [
            'title' => $template->title($locale),
            'content' => $render($template->content($locale)),
            'header' => $render($template->header($locale)),
            'footer' => $render($template->footer($locale)),
        ];
    }

    /**
     * The editor saves a blank line as an empty <p></p>, which has no height
     * outside the editor -- extra Enters the admin typed for spacing (e.g.
     * room for a signature) would vanish in the page and the PDF. A
     * non-breaking space gives each one a line's height. Done on output only,
     * so the stored HTML still round-trips through the editor unchanged.
     */
    public static function keepBlankLines(string $html): string
    {
        return (string) preg_replace('#<p(\s[^>]*)?></p>#', '<p$1>&nbsp;</p>', $html);
    }

    /**
     * Both languages side by side plus the page layout -- the shape the
     * Vue pages read (title.en, content.id, header.en, ..., page).
     *
     * @param  array<string, string|null>  $values
     */
    public function renderBilingual(FormTemplate $template, array $values): array
    {
        $en = $this->render($template, $values, 'en');
        $id = $this->render($template, $values, 'id');

        $document = [];

        foreach (['title', 'content', 'header', 'footer'] as $part) {
            $document[$part] = ['en' => $en[$part], 'id' => $id[$part]];
        }

        return [...$document, 'page' => $template->pageLayout()];
    }
}
