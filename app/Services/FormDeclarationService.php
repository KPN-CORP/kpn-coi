<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FormDeclaration;
use App\Models\FormTemplate;
use App\Models\NonEmployeeUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The signer's side of forms: what is waiting to be signed, and signing it.
 *
 * A person holds one valid signature per form type per period. A signed
 * template is locked, so changing its wording means duplicating it and
 * switching the copy on instead: the old signatures are kept but become
 * *superseded* (isSuperseded()) and their signers are asked to sign again.
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
     * Ids of the given declarations that are superseded: their template was
     * switched off and another template now applies to the signer (by the
     * business unit they signed under) for the same form type and period.
     *
     * Switching a template off with nothing replacing it (e.g. at the end of
     * a period) leaves its signatures valid -- only a newer version that
     * people must sign again invalidates the old ones.
     *
     * @param  iterable<FormDeclaration>  $declarations  with `template` loaded
     * @return array<int, true> keyed by declaration id
     */
    public function supersededIds(iterable $declarations): array
    {
        $available = [];
        $superseded = [];

        foreach ($declarations as $declaration) {
            $template = $declaration->template;

            // Still switched on: valid, whatever else applies.
            if ($template && $template->is_active && ! $template->trashed()) {
                continue;
            }

            $key = $declaration->form_type_id.'-'.$declaration->period;

            $available[$key] ??= FormTemplate::query()
                ->select(FormTemplate::SUMMARY_COLUMNS)
                ->available()
                ->where('form_type_id', $declaration->form_type_id)
                ->where('period', $declaration->period)
                ->get();

            $applicable = $this->templates->resolve($available[$key], $declaration->business_unit);

            if (self::replacedBy($declaration, $applicable)) {
                $superseded[$declaration->id] = true;
            }
        }

        return $superseded;
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
     * "I have read and agree" without signing yet. Kept on the same row the
     * signature will use, keyed like it (one per template).
     */
    public function saveDraft(User|NonEmployeeUser $account, FormTemplate $template, string $locale): FormDeclaration
    {
        return $this->onceOnly($account, $template, function (FormDeclaration $declaration) use ($account, $locale) {
            // Never downgrade: a draft request that lost a race with Sign
            // (two tabs, a retry) leaves the signature as it is.
            if ($declaration->isSigned()) {
                return $declaration;
            }

            $declaration->fill([
                'status' => FormDeclaration::STATUS_DRAFT,
                'locale' => $locale,
                'agreed_at' => now(),
                'business_unit' => $this->variables->businessUnitFor($account),
            ])->save();

            return $declaration;
        });
    }

    public function sign(
        User|NonEmployeeUser $account,
        FormTemplate $template,
        string $signedName,
        string $locale,
        Request $request
    ): FormDeclaration {
        return $this->onceOnly($account, $template, function (FormDeclaration $declaration) use ($account, $template, $signedName, $locale, $request) {
            // A second click / retry: the first one already signed.
            if ($declaration->isSigned()) {
                return $declaration;
            }

            $signedAt = now();

            // Turns an existing draft into the signature, or creates it.
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

    /**
     * Run a change to this account's single row for the template, serialised
     * against concurrent requests:
     *
     * - the template row is locked, so an admin edit/delete of the wording
     *   (FormTemplateController, which takes the same lock) cannot slip in
     *   between "still unsigned, editable" and the signature;
     * - the declaration row is locked when it exists; when it does not, two
     *   requests can both try to insert it, and the loser re-reads the
     *   winner's row instead of failing on the unique key.
     *
     * @param  callable(FormDeclaration): FormDeclaration  $change
     */
    private function onceOnly(User|NonEmployeeUser $account, FormTemplate $template, callable $change): FormDeclaration
    {
        $key = $this->rowKey($account, $template);

        try {
            return DB::transaction(function () use ($template, $key, $change) {
                FormTemplate::query()->whereKey($template->id)->lockForUpdate()->first();

                $declaration = FormDeclaration::query()->where($key)->lockForUpdate()->first()
                    ?? new FormDeclaration([
                        ...$key,
                        'form_type_id' => $template->form_type_id,
                        'period' => $template->period,
                    ]);

                return $change($declaration);
            });
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(function () use ($key, $change) {
                return $change(FormDeclaration::query()->where($key)->lockForUpdate()->firstOrFail());
            });
        }
    }

    /**
     * @return array{template_id: int, user_id: int, type: string}
     */
    private function rowKey(User|NonEmployeeUser $account, FormTemplate $template): array
    {
        return [
            'template_id' => $template->id,
            'user_id' => $account->id,
            'type' => self::typeOf($account),
        ];
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
