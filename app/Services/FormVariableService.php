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

    private const LOCALIZED_DATES = ['sign_date', 'date_of_birth'];

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
     * Raw values, locale-independent. sign_date and date_of_birth stay ISO and
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
                ? Carbon::parse($profile->date_of_joining)->format('d-m-Y')
                : null,
            'period' => (string) $period,
            'sign_date' => ($signedAt ?? now())->toDateString(),
        ];
    }

    /**
     * Replace every known {{key}} with its escaped value; blanks print as "-".
     * Unknown keys are left as written so a typo is visible, not silently lost.
     *
     * @param  array<string, string|null>  $values
     */
    public function render(string $html, array $values, string $locale): string
    {
        $known = $this->definitions();

        return (string) preg_replace_callback(
            self::PLACEHOLDER,
            function (array $match) use ($values, $known, $locale) {
                $key = $match[1];

                if (! array_key_exists($key, $known)) {
                    return $match[0];
                }

                $value = $values[$key] ?? null;

                // Kept ISO in the snapshot; written out in the reader's language.
                if (in_array($key, self::LOCALIZED_DATES, true) && filled($value)) {
                    $value = Carbon::parse($value)->locale($locale)->translatedFormat('j F Y');
                }

                return e(filled($value) ? (string) $value : '-');
            },
            $html
        );
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
