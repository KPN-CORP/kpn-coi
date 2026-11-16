<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\NonEmployeeCredentialMail;
use App\Models\Location;
use App\Models\NonEmployee;
use App\Models\NonEmployeeUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class CredentialImportService
{
    public function __construct(
        protected DataScopeService $dataScope
    ) {}

    /**
     * The admin running the import: their role's Contribution Level
     * restriction limits which companies a row may name, as on the form.
     */
    private function importer(): ?User
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * A company from $companies matching the cell by readable name or by
     * code, ignoring case and surrounding spaces.
     *
     * @param  Collection<int, array{code: string, name: string, business_unit: string}>  $companies
     * @return array{code: string, name: string, business_unit: string}|null
     */
    private function findCompany(Collection $companies, string $value): ?array
    {
        $needle = mb_strtolower(trim($value));

        return $companies->first(
            fn (array $company) => mb_strtolower((string) $company['name']) === $needle
                || mb_strtolower((string) $company['code']) === $needle
        );
    }

    /**
     * Stored like kpncorp.employees: the code plus the readable name.
     *
     * @return array{contribution_level_code: ?string, company_name: ?string}
     */
    private function companyAttributes(string $businessUnit, string $value): array
    {
        $company = $value === ''
            ? null
            : $this->findCompany(
                $this->dataScope->companyOptionsFor($this->importer(), $businessUnit),
                $value
            );

        return [
            'contribution_level_code' => $company['code'] ?? null,
            'company_name' => $company['name'] ?? null,
        ];
    }

    /**
     * The office area of $businessUnit matching the cell, ignoring case and
     * surrounding spaces, spelled as locations.area has it -- the same list
     * the form's Office Location dropdown offers for that unit.
     */
    private function findOfficeArea(string $businessUnit, string $value): ?string
    {
        $needle = mb_strtolower(trim($value));

        $this->officeAreas ??= Location::officeAreaOptions();

        return $this->officeAreas
            ->where('business_unit', $businessUnit)
            ->first(fn (array $option) => mb_strtolower((string) $option['office_area']) === $needle)['office_area'] ?? null;
    }

    /** @var Collection<int, array{business_unit: string, office_area: string}>|null */
    private ?Collection $officeAreas = null;

    /**
     * @return array{office_area: ?string, work_area_code: ?string}
     */
    private function officeAttributes(string $businessUnit, string $value): array
    {
        $area = $value === '' ? null : $this->findOfficeArea($businessUnit, $value);

        return [
            'office_area' => $area,
            'work_area_code' => Location::workAreaFor($businessUnit, $area),
        ];
    }

    /**
     * Read a Date of Join from a spreadsheet cell. Excel stores date-formatted
     * cells as a serial number (e.g. 45582), so a plain text parse fails; that
     * case is handled here alongside text in dd-mm-yyyy or dd/mm/yyyy. Returns
     * null when the value is not a usable date.
     */
    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Date-formatted cells come through as a numeric serial, not text.
        if (is_numeric($value)) {
            try {
                return Carbon::instance(
                    ExcelDate::excelToDateTimeObject((float) $value)
                );
            } catch (\Throwable) {
                return null;
            }
        }

        $text = trim((string) $value);

        foreach (['d-m-Y', 'd/m/Y', 'd.m.Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $text);
            } catch (\Throwable) {
                continue;
            }

            // Guard against lenient parsing accepting e.g. 32-13-2024.
            if ($date !== false && $date->format($format) === $text) {
                return $date;
            }
        }

        return null;
    }

    /**
     * Nationality is stored as a country name to match the form. A blank cell
     * or an "Indonesia"/"Indonesian" value is treated as a local; anything else
     * is kept as the foreign country the row provided.
     */
    private function resolveNationality(mixed $value): string
    {
        $nationality = trim((string) $value);

        if (
            $nationality === ''
            || in_array(strtolower($nationality), ['indonesia', 'indonesian'], true)
        ) {
            return 'Indonesia';
        }

        return $nationality;
    }

    /**
     * All or nothing: when any row fails, nothing is written and the failed
     * rows come back as `errors` (also kept in the session for the error
     * report). Otherwise every row is created, updated, or left unchanged,
     * reported per row in `results`.
     *
     * @return array{errors: list<array<string, mixed>>, results: list<array{row: int, name: string, email: string, status: string, changes: list<string>}>}
     */
    public function import(
        Collection $rows
    ): array {

        $errors = collect();

        $emails = [];

        $citizenNumbers = [];

        // Row index => the existing account it updates, or null for new.
        $targets = [];

        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        foreach ($rows as $index => $row) {

            $line = $index + 2;

            $email = trim((string) $row['email']);

            $citizenNumber = trim((string) $row['citizen_number_passport_number']);

            $businessUnit = trim((string) $row['business_unit']);

            $companyName = trim((string) ($row['company_name'] ?? ''));

            $officeLocation = trim((string) ($row['office_location'] ?? ''));

            $nik = trim((string) ($row['nik'] ?? ''));

            $parsedDoj = $this->parseDate($row['date_of_join'] ?? null);

            // One error-report row per failed line, in CredentialImportErrorExport's
            // column order. Show a readable date even when the cell held an
            // Excel serial number.
            $fail = fn (string $message) => $errors->push([
                'row' => $line,
                'name' => trim((string) $row['fullname']),
                'nik' => $nik,
                'email' => $email,
                'citizen_number' => $citizenNumber,
                'business_unit' => $businessUnit,
                'company_name' => $companyName,
                'office_location' => $officeLocation,
                'date_of_join' => $parsedDoj
                    ? $parsedDoj->format('d-m-Y')
                    : trim((string) $row['date_of_join']),
                'address' => trim((string) $row['permanent_address']),
                'error' => $message,
            ]);

            if (blank($row['fullname'])) {
                $fail('Fullname is required.');

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fail('Invalid email.');

                continue;
            }

            // Same rule as the form: optional, digits only (kept as text so a
            // leading zero survives).
            if ($nik !== '' && (! ctype_digit($nik) || strlen($nik) > 50)) {
                $fail('NIK must contain digits only.');

                continue;
            }

            if (blank($row['business_unit'])) {
                $fail('Select Business Unit.');

                continue;
            }

            // Same rule as the Add User form: required when the unit has any
            // company, and it must be one of that unit's (or, for KPN
            // Corporation, any company). Matched by name or code.
            $companies = $this->dataScope->companyOptionsFor($this->importer(), $businessUnit);

            if ($companyName === '' && $companies->isNotEmpty()) {
                $fail('Company Name is required.');

                continue;
            }

            if ($companyName !== '' && $this->findCompany($companies, $companyName) === null) {
                $fail('Company Name does not belong to this Business Unit.');

                continue;
            }

            // Optional, as on the form, but when given it must be one of the
            // unit's office locations (the Office Locations sheet), which the
            // form's dropdown also limits it to.
            if ($officeLocation !== '' && $this->findOfficeArea($businessUnit, $officeLocation) === null) {
                $fail('Office Location does not belong to this Business Unit.');

                continue;
            }

            if (blank($row['date_of_join'])) {
                $fail('DOJ Cannot be Empty.');

                continue;
            }

            if ($parsedDoj === null) {
                $fail('Date of Joining must be a valid date (dd-mm-yyyy or dd/mm/yyyy).');

                continue;
            }

            if (isset($emails[mb_strtolower($email)])) {
                $fail('Duplicate email in uploaded file.');

                continue;
            }

            if ($citizenNumber !== '' && isset($citizenNumbers[$citizenNumber])) {
                $fail('Duplicate Citizenship Number / Passport ID in uploaded file.');

                continue;
            }

            $emails[mb_strtolower($email)] = true;

            $citizenNumbers[$citizenNumber] = true;

            // An account that already exists is updated instead of rejected.
            // It is found by email or by KTP (the identity that survives an
            // email change); both pointing at different accounts is a conflict.
            $byEmail = NonEmployeeUser::query()->where('email', $email)->first();

            $byKtp = $citizenNumber === ''
                ? null
                : NonEmployee::query()->where('ktp', $citizenNumber)->first()?->user;

            if ($byEmail && $byKtp && $byEmail->id !== $byKtp->id) {
                $fail('Email and Citizenship Number / Passport ID belong to different users.');

                continue;
            }

            $existing = $byEmail ?? $byKtp;

            if ($existing === null) {
                $targets[$index] = null;

                continue;
            }

            // Converted to an employee: the profile now comes from HRIS and
            // the Credentials page no longer lists it, so it can't be edited.
            if ($existing->employee_id !== null) {
                $fail('This user has been converted to an employee and can no longer be updated here.');

                continue;
            }

            // Same visibility as the Credentials list: a restricted admin may
            // only touch the people their role lets them see.
            if (
                $this->dataScope->isRestricted($this->importer())
                && ! $this->dataScope
                    ->applyToPeople(NonEmployee::query()->where('user_id', $existing->id), $this->importer())
                    ->exists()
            ) {
                $fail('This user already exists outside your data access.');

                continue;
            }

            $targets[$index] = $existing;
        }

        if ($errors->isNotEmpty()) {

            session()->put(
                'credential_import_errors',
                $errors
            );

            return [
                'errors' => $errors->values()->all(),
                'results' => [],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Import
        |--------------------------------------------------------------------------
        */

        session()->forget('credential_import_errors');

        $results = DB::transaction(function () use ($rows, $targets) {

            $results = [];

            foreach ($rows as $index => $row) {

                $account = [
                    'name' => trim((string) $row['fullname']),
                    'email' => trim((string) $row['email']),
                ];

                $profile = $this->profileAttributes($row);

                $existing = $targets[$index] ?? null;

                $result = [
                    'row' => $index + 2,
                    'name' => $account['name'],
                    'email' => $account['email'],
                ];

                if ($existing === null) {
                    $this->create($account, $profile);

                    $results[] = [...$result, 'status' => 'created', 'changes' => []];

                    continue;
                }

                // Every column the file carries is compared; only a row with
                // a real difference is written, the rest is reported as
                // unchanged, so re-uploading the same file changes nothing.
                $existing->fill($account);

                $nonEmployee = $existing->employee ?? new NonEmployee(['user_id' => $existing->id]);
                $nonEmployee->fill($profile);

                $changes = collect([...array_keys($existing->getDirty()), ...array_keys($nonEmployee->getDirty())])
                    ->map(fn (string $column) => self::FIELD_LABELS[$column] ?? $column)
                    ->unique()
                    ->values()
                    ->all();

                if ($changes === []) {
                    $results[] = [...$result, 'status' => 'unchanged', 'changes' => []];

                    continue;
                }

                $existing->save();
                $nonEmployee->save();

                $results[] = [...$result, 'status' => 'updated', 'changes' => $changes];
            }

            return $results;
        });

        return [
            'errors' => [],
            'results' => $results,
        ];
    }

    /**
     * Readable names of the columns an update can change, for the result list.
     * users.name / users.email mirror fullname / email, hence the shared labels.
     */
    private const FIELD_LABELS = [
        'name' => 'Fullname',
        'fullname' => 'Fullname',
        'email' => 'Email',
        'employee_id' => 'NIK',
        'personal_mobile_number' => 'Phone Number',
        'ktp' => 'Citizen Number / Passport Number',
        'nationality' => 'Nationality',
        'group_company' => 'Business Unit',
        'contribution_level_code' => 'Company Name',
        'company_name' => 'Company Name',
        'office_area' => 'Office Location',
        'work_area_code' => 'Office Location',
        'date_of_joining' => 'Date of Join',
        'permanent_address' => 'Permanent Address',
    ];

    /**
     * The non_employees columns a template row sets, built the same way on
     * create and on update so an unchanged row compares equal.
     */
    private function profileAttributes(Collection|array $row): array
    {
        $businessUnit = trim((string) $row['business_unit']);

        return [
            'employee_id' => trim((string) ($row['nik'] ?? '')) ?: null,

            'fullname' => trim((string) $row['fullname']),

            'email' => trim((string) $row['email']),

            'personal_mobile_number' => trim((string) ($row['phone_number'] ?? '')) ?: null,

            'ktp' => trim((string) $row['citizen_number_passport_number']),

            'nationality' => $this->resolveNationality($row['nationality'] ?? null),

            'group_company' => $businessUnit,

            // Validated above, so the lookup always finds it when set.
            ...$this->companyAttributes(
                $businessUnit,
                trim((string) ($row['company_name'] ?? '')),
            ),

            // Validated above, so a given office location is one of the
            // unit's; stored with locations.area's spelling so the work_area
            // lookup (exact match) finds it.
            ...$this->officeAttributes(
                $businessUnit,
                trim((string) ($row['office_location'] ?? '')),
            ),

            'date_of_joining' => $this->parseDate($row['date_of_join'] ?? null)?->format('Y-m-d'),

            'permanent_address' => trim((string) $row['permanent_address']),
        ];
    }

    /**
     * A new account plus its profile; the login is mailed once committed.
     */
    private function create(array $account, array $profile): void
    {
        $password = Str::password(12);

        $user = NonEmployeeUser::query()->create([
            ...$account,
            'password' => Hash::make($password),
        ]);

        NonEmployee::query()->create([
            'user_id' => $user->id,
            ...$profile,
        ]);

        DB::afterCommit(fn () => Mail::to($user->email)->queue(
            new NonEmployeeCredentialMail($user, $password)
        ));
    }
}
