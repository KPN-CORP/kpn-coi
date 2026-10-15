<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Companies;
use App\Models\Employee;
use App\Models\NonEmployee;
use App\Models\NonEmployeeUser;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Fills the {{variable}} placeholders of a form template.
 *
 * Values come from the signer's own profile row -- an HRIS Employee for an SSO
 * account, a local NonEmployee for the rest. Both are reached through the
 * account's `employee` relation, which points at the right database for each
 * type; never resolve one account's profile through the other's model.
 */
class FormVariableService
{
    private const PLACEHOLDER = '/\{\{\s*([a-z_]+)\s*\}\}/';

    /**
     * The optional signature block. Printed only where a template places it --
     * the template owns its signature layout, nothing is added for it.
     */
    public const SIGNATURE = 'signature';

    /**
     * Times are shown in Jakarta time, like the COI declaration PDF -- the
     * app itself runs in UTC.
     */
    public const DISPLAY_TIMEZONE = 'Asia/Jakarta';

    /**
     * Date variables: kept ISO in values/snapshots and written out in the
     * reader's language by displayValues(). `sign_date` is a date and time
     * and is formatted separately.
     */
    public const LOCALIZED_DATES = ['date_of_birth', 'date_of_joining'];

    /** @var array<string, string>|null contribution_level_code -> name */
    private ?array $companyNames = null;

    /**
     * @return array<string, array{en: string, id: string}> key => label per locale
     */
    public function definitions(): array
    {
        return config('forms.variables', []);
    }

    public function profileFor(User|NonEmployeeUser $account): Employee|NonEmployee|null
    {
        return $account->employee;
    }

    public function businessUnitFor(User|NonEmployeeUser $account): ?string
    {
        $unit = trim((string) $this->profileFor($account)?->group_company);

        return $unit === '' ? null : $unit;
    }

    /**
     * The name a signer must retype. Falls back to the account name for an
     * account without a profile row so it can still sign.
     */
    public function fullNameFor(User|NonEmployeeUser $account): string
    {
        return (string) ($this->profileFor($account)?->fullname ?: $account->name);
    }

    /**
     * Raw values, locale-independent. Dates (LOCALIZED_DATES) stay ISO and
     * are formatted per language in render().
     *
     * @return array<string, string|null>
     */
    public function resolve(
        User|NonEmployeeUser $account,
        int $period,
        ?Carbon $signedAt = null
    ): array {
        $profile = $this->profileFor($account);

        $isEmployee = $profile instanceof Employee;

        $workLocation = $profile?->office_area;

        if (! $workLocation && $profile instanceof NonEmployee) {
            $workLocation = $profile->location?->area;
        }

        return [
            'employee_name' => $this->fullNameFor($account),
            'employee_id' => $profile?->employee_id,
            'id_number' => $profile?->ktp,
            'place_of_birth' => $profile?->place_of_birth,
            'date_of_birth' => $profile?->date_of_birth
                ? Carbon::parse($profile->date_of_birth)->toDateString()
                : null,
            'address' => $profile?->permanent_address,
            'email' => $profile?->email ?: $account->email,
            'job_title' => $profile?->designation_name,
            'department' => $isEmployee ? $profile->unit : null,
            'company' => $this->companyName($profile?->contribution_level_code),
            'business_unit' => $profile?->group_company,
            'work_location' => $workLocation,
            'date_of_joining' => $profile?->date_of_joining
                ? Carbon::parse($profile->date_of_joining)->toDateString()
                : null,
            'period' => (string) $period,
            // Date and time; before signing it is "now", i.e. what it would say.
            'sign_date' => ($signedAt ?? now())->toIso8601String(),
        ];
    }

    /**
     * Replace every known {{key}} with its display HTML. Unknown keys are left
     * as written so a typo is visible, not silently lost.
     *
     * @param  array<string, string|null>  $values
     */
    public function render(string $html, array $values, string $locale): string
    {
        $display = $this->displayValues($values, $locale);

        return (string) preg_replace_callback(
            self::PLACEHOLDER,
            fn (array $match) => $display[$match[1]] ?? $match[0],
            $html
        );
    }

    /**
     * Every variable as ready-to-insert HTML in one language: text escaped,
     * blanks as "-", dates written out, the signature as its block. This is
     * the one place values are formatted -- the template editor's preview
     * uses this output too rather than formatting again in the browser.
     *
     * Pass `signed_name` (with the signing time as `sign_date`) for a real
     * signature; without it {{signature}} marks where it will appear.
     *
     * @param  array<string, string|null>  $values
     * @return array<string, string>
     */
    public function displayValues(array $values, string $locale): array
    {
        $display = [];

        foreach (array_keys($this->definitions()) as $key) {
            $value = $values[$key] ?? null;

            $display[$key] = match (true) {
                $key === self::SIGNATURE => $this->signatureHtml($values, $locale),
                $key === 'sign_date' && filled($value) => e($this->dateTime((string) $value, $locale)),
                in_array($key, self::LOCALIZED_DATES, true) && filled($value) => e($this->date((string) $value, $locale)),
                default => e(filled($value) ? (string) $value : '-'),
            };
        }

        return $display;
    }

    /**
     * The name the signer typed, in the COI PDF's handwriting face.
     *
     * @param  array<string, string|null>  $values
     */
    private function signatureHtml(array $values, string $locale): string
    {
        $id = $locale === 'id';
        $name = trim((string) ($values['signed_name'] ?? ''));

        if ($name === '') {
            return '<span class="form-signature-placeholder">'.e($id
                ? 'Tanda tangan Anda akan muncul di sini setelah Anda menandatangani'
                : 'Your signature will appear here once you sign').'</span>';
        }

        // Only the typed name, in handwriting: "Declared by", name and date
        // lines belong to the template's own layout.
        return '<span class="form-signature-name">'.e($name).'</span>';
    }

    /** "27 Juli 2026 Pukul 10:38:03" -- the COI declaration's format. */
    private function dateTime(string $value, string $locale): string
    {
        $time = Carbon::parse($value)->timezone(self::DISPLAY_TIMEZONE)->locale($locale === 'id' ? 'id' : 'en');

        // A date-only value (snapshots from before sign_date carried a time).
        if (strlen($value) <= 10) {
            return Carbon::parse($value)->locale($locale === 'id' ? 'id' : 'en')->translatedFormat('d F Y');
        }

        return $time->translatedFormat('d F Y').' '.($locale === 'id' ? 'Pukul' : 'at').' '.$time->format('H:i:s');
    }

    private function date(string $value, string $locale): string
    {
        return Carbon::parse($value)->locale($locale === 'id' ? 'id' : 'en')->translatedFormat('d F Y');
    }

    private function companyName(?string $code): ?string
    {
        if (! filled($code)) {
            return null;
        }

        $this->companyNames ??= Companies::query()
            ->whereNotNull('contribution_level_code')
            ->pluck('contribution_level', 'contribution_level_code')
            ->all();

        return $this->companyNames[$code] ?? null;
    }
}
