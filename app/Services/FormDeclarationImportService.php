<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\FormDeclaration;
use App\Models\FormTemplate;
use App\Models\FormType;
use App\Models\ImportLog;
use App\Models\NonEmployee;
use App\Models\NonEmployeeUser;
use App\Models\User;
use App\Support\FormPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import Center: load form signatures made outside the app (on paper, in an
 * earlier system) from a spreadsheet of "who, signed yes/no".
 *
 * Each row names a person by employee_id or email. Employees are looked up in
 * the HRIS (kpncorp) first, then non-employees in the app database -- a
 * converted non-employee's email can match both and the employee account is
 * the current identity. A "Yes" row becomes a signed declaration on the
 * template that applies to that person for the row's Year and Month, exactly
 * as if they had signed it in the app (same template resolution, same
 * variable snapshot), marked source = import. Month may be left empty: the
 * person is then signed on the latest template of that year that applies to
 * them -- the one the yearly report and dashboard count. A "No" row changes
 * nothing: nothing is ever deleted, so a person who signed stays signed.
 *
 * Row by row: valid rows are imported and invalid ones are reported back with
 * the reason (ImportLog keeps the outcome of every row). Re-uploading the
 * corrected file is safe -- a person who already signed that template is
 * skipped, not signed twice.
 */
class FormDeclarationImportService
{
    private const YES = ['yes', 'y', 'ya', 'true', '1', 'signed', 'sudah'];

    private const NO = ['no', 'n', 'tidak', 'false', '0', 'not signed', 'belum'];

    /** Month names a spreadsheet may carry instead of 1-12 (EN and ID). */
    private const MONTHS = [
        'jan' => 1, 'january' => 1, 'januari' => 1,
        'feb' => 2, 'february' => 2, 'februari' => 2,
        'mar' => 3, 'march' => 3, 'maret' => 3,
        'apr' => 4, 'april' => 4,
        'may' => 5, 'mei' => 5,
        'jun' => 6, 'june' => 6, 'juni' => 6,
        'jul' => 7, 'july' => 7, 'juli' => 7,
        'aug' => 8, 'august' => 8, 'agu' => 8, 'agustus' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9,
        'oct' => 10, 'october' => 10, 'okt' => 10, 'oktober' => 10,
        'nov' => 11, 'november' => 11,
        'dec' => 12, 'december' => 12, 'des' => 12, 'desember' => 12,
    ];

    public function __construct(
        private readonly DataScopeService $dataScope,
        private readonly FormTemplateService $templates,
        private readonly FormVariableService $variables,
    ) {}

    /**
     * Periods (YYYYMM) that have an available template of this type, newest
     * first -- the only periods an import can sign against. Shown on the
     * page as a hint for filling in Year and Month.
     *
     * @return list<int>
     */
    public function periods(FormType $formType): array
    {
        return FormTemplate::query()
            ->available()
            ->where('form_type_id', $formType->id)
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period')
            ->map(fn ($period) => (int) $period)
            ->all();
    }

    /**
     * Import what can be imported and report every row.
     *
     * @param  Collection<int, Collection|array>  $rows  heading-keyed spreadsheet rows
     * @return list<array{row: int, identifier: string, name: ?string, year: string, month: string, signed: string, signed_date: string, status: string, message: string}>
     */
    public function import(Collection $rows, FormType $formType, User $importer, string $locale = 'en'): array
    {
        $rows = $rows->map(fn ($row) => collect($row));

        // Every available template of the type, grouped by period, newest
        // period first -- resolved per row without querying again.
        $templatesByPeriod = FormTemplate::query()
            ->select(FormTemplate::SUMMARY_COLUMNS)
            ->available()
            ->where('form_type_id', $formType->id)
            ->orderByDesc('period')
            ->get()
            ->groupBy(fn (FormTemplate $template) => (int) $template->period);

        $accounts = $this->lookUp($rows, $importer);

        $results = [];
        $seen = [];
        $toSign = [];

        foreach ($rows as $index => $row) {
            // Row 1 is the heading.
            $line = $index + 2;

            $identifier = trim((string) ($row['employee_id_or_email'] ?? $row['employee_id'] ?? $row['email'] ?? $row['employee_email'] ?? ''));
            $signedText = trim((string) ($row['signed'] ?? ''));

            $results[$line] = [
                'row' => $line,
                'identifier' => $identifier,
                'name' => null,
                'year' => trim((string) ($row['year'] ?? '')),
                'month' => trim((string) ($row['month'] ?? '')),
                'signed' => $signedText,
                'signed_date' => $this->displayCell($row['signed_date'] ?? null),
                'status' => ImportLog::ROW_FAILED,
                'message' => '',
            ];

            $fail = function (string $message) use (&$results, $line) {
                $results[$line]['message'] = $message;
            };

            if ($identifier === '') {
                $fail('Employee ID or email is required.');

                continue;
            }

            $signed = $this->parseSigned($signedText);

            if ($signed === null) {
                $fail('Signed must be Yes or No.');

                continue;
            }

            $account = $accounts[mb_strtolower($identifier)] ?? null;

            if ($account === null) {
                $fail('No employee or non-employee with this ID or email within your data access.');

                continue;
            }

            $results[$line]['name'] = $this->variables->fullNameFor($account);

            $year = $this->parseYear($row['year'] ?? null);

            if ($year === null) {
                $fail('Year is required, as a 4-digit year (e.g. 2026).');

                continue;
            }

            $month = $this->parseMonth($row['month'] ?? null);

            if ($month === false) {
                $fail('Month must be 1-12 (or a month name), or left empty.');

                continue;
            }

            $key = FormDeclarationService::typeOf($account).'-'.$account->id.'-'.$year.'-'.($month ?? '*');

            if (isset($seen[$key])) {
                $fail("Same person, year and month as row {$seen[$key]}.");

                continue;
            }

            $seen[$key] = $line;

            if (! $signed) {
                $results[$line]['status'] = ImportLog::ROW_NOT_SIGNED;
                $results[$line]['message'] = 'Marked No: nothing changed.';

                continue;
            }

            $signedAt = $this->parseSignedAt($row['signed_date'] ?? null);

            if ($signedAt === false) {
                $fail('Signed Date must be a valid date (dd-mm-yyyy, optionally with HH:mm) not in the future.');

                continue;
            }

            $template = $this->templateFor($templatesByPeriod, $account, $year, $month);

            if ($template === null) {
                $fail(sprintf(
                    'No active %s template applies to this person for %s.',
                    $formType->name_en,
                    $month ? FormPeriod::label(FormPeriod::make($year, $month)) : $year,
                ));

                continue;
            }

            $toSign[] = compact('line', 'account', 'template', 'signedAt', 'locale');
        }

        foreach ($this->sign($toSign, $formType, $importer) as $line => $imported) {
            $period = FormPeriod::label((int) collect($toSign)->firstWhere('line', $line)['template']->period);

            $results[$line]['status'] = $imported ? ImportLog::ROW_IMPORTED : ImportLog::ROW_ALREADY_SIGNED;
            $results[$line]['message'] = $imported
                ? "Signed for {$period}."
                : "Already signed for {$period}: skipped.";
        }

        return array_values($results);
    }

    /**
     * Write the signatures with the templates' rows locked -- the same lock
     * signing in the app and editing a template take (FormDeclarationService::
     * locked(), FormTemplateController), so an import cannot interleave with
     * either. Someone who already signed the very template is skipped; a
     * draft of it becomes the signature, as when signing in the app.
     *
     * @param  list<array{line: int, account: User|NonEmployeeUser, template: FormTemplate, signedAt: ?Carbon, locale: string}>  $toSign
     * @return array<int, bool> per spreadsheet line: signed now (true) or already signed (false)
     */
    private function sign(array $toSign, FormType $formType, User $importer): array
    {
        if ($toSign === []) {
            return [];
        }

        return DB::transaction(function () use ($toSign, $formType, $importer) {
            $templateIds = collect($toSign)->map(fn (array $item) => $item['template']->id)->unique()->values();

            FormTemplate::query()->whereKey($templateIds)->lockForUpdate()->get(['id']);

            $existing = FormDeclaration::query()
                ->where('form_type_id', $formType->id)
                ->whereIn('template_id', $templateIds)
                ->get(['id', 'user_id', 'type', 'template_id', 'status', 'agreed_at']);

            $keyOf = fn (string $type, int $userId, int $templateId) => "{$type}-{$userId}-{$templateId}";

            $signed = $existing->filter->isSigned()
                ->mapWithKeys(fn (FormDeclaration $row) => [$keyOf($row->type, (int) $row->user_id, (int) $row->template_id) => true]);

            $drafts = $existing->reject->isSigned()
                ->keyBy(fn (FormDeclaration $row) => $keyOf($row->type, (int) $row->user_id, (int) $row->template_id));

            $outcome = [];

            foreach ($toSign as ['line' => $line, 'account' => $account, 'template' => $template, 'signedAt' => $signedAt, 'locale' => $locale]) {
                $type = FormDeclarationService::typeOf($account);
                $key = $keyOf($type, $account->id, $template->id);

                if ($signed->has($key)) {
                    $outcome[$line] = false;

                    continue;
                }

                $signedAt ??= now();

                $declaration = $drafts->get($key) ?? new FormDeclaration([
                    'template_id' => $template->id,
                    'form_type_id' => $template->form_type_id,
                    'period_year' => $template->period_year,
                    'period_month' => $template->period_month,
                    'user_id' => $account->id,
                    'type' => $type,
                ]);

                $declaration->fill([
                    'status' => FormDeclaration::STATUS_SIGNED,
                    'business_unit' => $this->variables->businessUnitFor($account),
                    'locale' => $locale,
                    'agreed_at' => $declaration->agreed_at ?? $signedAt,
                    'signed_name' => FormDeclarationService::normalizeName($this->variables->fullNameFor($account)),
                    'variables' => $this->variables->resolve($account, $template->period, $signedAt),
                    'signed_at' => $signedAt,
                    'ip_address' => null,
                    'user_agent' => null,
                    'source' => FormDeclaration::SOURCE_IMPORT,
                    'imported_by' => $importer->id,
                ])->save();

                // Two rows of one person can land on the same template (one
                // with the month, one without): sign it once.
                $signed->put($key, true);
                $drafts->forget($key);

                $outcome[$line] = true;
            }

            return $outcome;
        });
    }

    /** A cell as the admin typed it, for the result report. */
    private function displayCell(mixed $value): string
    {
        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('d-m-Y H:i');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return trim((string) $value);
    }

    /**
     * Every identifier in the file resolved to an account, keyed by the
     * lower-cased identifier. Lookups are batched per database -- the two
     * users tables live on different connections and can't be joined.
     * People outside the importer's data restrictions are left out.
     *
     * @return array<string, User|NonEmployeeUser>
     */
    private function lookUp(Collection $rows, User $importer): array
    {
        $identifiers = $rows
            ->map(fn (Collection $row) => mb_strtolower(trim((string) ($row['employee_id_or_email'] ?? $row['employee_id'] ?? $row['email'] ?? $row['employee_email'] ?? ''))))
            ->filter()
            ->unique()
            ->values();

        $found = [];

        foreach ($identifiers->chunk(500) as $chunk) {
            $chunk = $chunk->values()->all();

            // Employees: kpncorp users.id is employees.id. A rehired person
            // has one account per stint under the same email; the newest
            // stint is the current one.
            $users = User::query()
                ->with('employee')
                ->where(fn ($query) => $query->whereIn('employee_id', $chunk)->orWhereIn('email', $chunk))
                ->orderBy('id')
                ->get();

            $allowed = $this->dataScope->isRestricted($importer)
                ? $this->dataScope->applyToPeople(
                    Employee::query()->whereIn('id', $users->pluck('id')),
                    $importer
                )->pluck('id')->flip()
                : null;

            foreach ($users as $user) {
                if ($allowed !== null && ! $allowed->has($user->id)) {
                    continue;
                }

                foreach ([$user->employee_id, $user->email, $user->employee?->email] as $value) {
                    if (filled($value)) {
                        $found[mb_strtolower(trim((string) $value))] = $user;
                    }
                }
            }

            $remaining = array_values(array_filter($chunk, fn (string $identifier) => ! isset($found[$identifier])));

            if ($remaining === []) {
                continue;
            }

            // Non-employees: by their NIK (non_employees.employee_id) or email.
            $profiles = $this->dataScope->applyToPeople(
                NonEmployee::query()
                    ->whereNotNull('user_id')
                    ->where(fn ($query) => $query->whereIn('employee_id', $remaining)->orWhereIn('email', $remaining)),
                $importer
            )->get(['user_id', 'employee_id', 'email']);

            $accounts = NonEmployeeUser::query()
                ->with('employee.location')
                ->whereIn('id', $profiles->pluck('user_id'))
                ->get()
                ->keyBy('id');

            foreach ($profiles as $profile) {
                $account = $accounts->get($profile->user_id);

                if (! $account) {
                    continue;
                }

                foreach ([$profile->employee_id, $profile->email, $account->email] as $value) {
                    $value = mb_strtolower(trim((string) $value));

                    if ($value !== '' && in_array($value, $remaining, true)) {
                        $found[$value] ??= $account;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * The template this person signs for the row: that month's, or with no
     * month the latest period of the year that has one applying to them.
     *
     * @param  Collection<int, Collection<int, FormTemplate>>  $templatesByPeriod  newest period first
     */
    private function templateFor(Collection $templatesByPeriod, User|NonEmployeeUser $account, int $year, ?int $month): ?FormTemplate
    {
        $businessUnit = $this->variables->businessUnitFor($account);

        $periods = $month
            ? [FormPeriod::make($year, $month)]
            : $templatesByPeriod->keys()->filter(fn (int $period) => FormPeriod::year($period) === $year)->all();

        foreach ($periods as $period) {
            $template = $this->templates->resolve($templatesByPeriod->get($period, collect()), $businessUnit);

            if ($template) {
                return $template;
            }
        }

        return null;
    }

    private function parseYear(mixed $value): ?int
    {
        $value = trim((string) $value);

        if (! preg_match('/^\d{4}$/', $value)) {
            return null;
        }

        $year = (int) $value;

        return $year >= 2000 && $year <= 2100 ? $year : null;
    }

    /**
     * Null when empty (the whole year), false when unusable.
     */
    private function parseMonth(mixed $value): int|false|null
    {
        $value = mb_strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}$/', $value)) {
            $month = (int) $value;

            return $month >= 1 && $month <= 12 ? $month : false;
        }

        return self::MONTHS[$value] ?? false;
    }

    private function parseSigned(string $value): ?bool
    {
        $value = mb_strtolower($value);

        return match (true) {
            in_array($value, self::YES, true) => true,
            in_array($value, self::NO, true) => false,
            default => null,
        };
    }

    /**
     * The optional Signed Date, read as Jakarta time (as the app displays
     * it) and returned in the app timezone for storage. Null when blank --
     * the import time is used; false when unusable or in the future.
     */
    private function parseSignedAt(mixed $value): Carbon|false|null
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $zone = FormVariableService::DISPLAY_TIMEZONE;
        $date = null;

        // Date-formatted cells come through as an Excel serial number.
        if (is_numeric($value)) {
            try {
                $date = Carbon::parse(
                    ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s'),
                    $zone
                );
            } catch (\Throwable) {
                return false;
            }
        } else {
            $text = preg_replace('/\s+/', ' ', trim((string) $value));

            foreach (['d-m-Y H:i', 'd/m/Y H:i', 'Y-m-d H:i', 'd-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
                try {
                    $parsed = Carbon::createFromFormat('!'.$format, $text, $zone);
                } catch (\Throwable) {
                    continue;
                }

                // Guard against lenient parsing accepting e.g. 32-13-2024.
                if ($parsed !== false && $parsed->format($format) === $text) {
                    $date = $parsed;

                    break;
                }
            }
        }

        if ($date === null || $date->isFuture()) {
            return false;
        }

        return $date->setTimezone(config('app.timezone'));
    }
}
