<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\NonEmployeeCredentialMail;
use App\Models\Location;
use App\Models\NonEmployee;
use App\Models\NonEmployeeUser;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class CredentialImportService
{
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
                $date = Carbon::createFromFormat('!' . $format, $text);
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

    public function import(
        Collection $rows
    ): void {

        $errors = collect();

        $emails = [];

        $citizenNumbers = [];

        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        foreach ($rows as $index => $row) {

            $line = $index + 2;

            $email = trim(
                (string) $row['email']
            );

            $name = trim(
                (string) $row['fullname']
            );

            $citizenNumber = trim(
                (string) $row['citizen_number_passport_number']
            );

            $businessUnit = trim(
                (string) $row['business_unit']
            );
            $parsedDoj = $this->parseDate($row['date_of_join'] ?? null);

            // Show a readable date in error reports even when the cell held an
            // Excel serial number.
            $doj = $parsedDoj
                ? $parsedDoj->format('d-m-Y')
                : trim((string) $row['date_of_join']);

            $address = trim(
                (string) $row['permanent_address']
            );

            if (blank($row['fullname'])) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'business_unit' => $businessUnit,

                    'date_of_join' => $doj,

                    'address' => $address,

                    'error' => 'Email already exists.',

                ]);

                continue;

            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'business_unit' => $businessUnit,
                    
                    'date_of_join' => $doj,

                    'address' => $address,

                    'error' => 'Invalid email.',

                ]);

                continue;

            }

            if (blank($row['business_unit'])) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'business_unit' => $businessUnit,
                    
                    'date_of_join' => $doj,

                    'address' => $address,

                    'error' => 'Select Business Unit.',

                ]);

                continue;

            }

            if (blank($row['date_of_join'])) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'business_unit' => $businessUnit,
                    
                    'date_of_join' => $doj,

                    'address' => $address,

                    'error' => 'DOJ Cannot be Empty.',

                ]);

                continue;

            }

            if ($parsedDoj === null) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'business_unit' => $businessUnit,

                    'date_of_join' => $doj,

                    'address' => $address,

                    'error' => 'Date of Joining must be a valid date (dd-mm-yyyy or dd/mm/yyyy).',

                ]);

                continue;
            }

            if (
                isset(
                    $emails[$email]
                )
            ) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'business_unit' => $businessUnit,
                    
                    'date_of_join' => $doj,

                    'address' => $address,

                    'error' => 'Duplicate email in uploaded file.',

                ]);

                continue;

            }

            if (
                isset(
                    $citizenNumbers[$citizenNumber]
                )
            ) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'business_unit' => $businessUnit,
                    
                    'date_of_join' => $doj,

                    'address' => $address,

                    'error' => 'Duplicate Citizenship Number / Passport ID in uploaded file.',

                ]);

                continue;

            }

            $emails[$email] = true;

            $citizenNumbers[$citizenNumber] = true;

            if (
                NonEmployeeUser::query()
                    ->where(
                        'email',
                        $email
                    )
                    ->exists()
            ) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'business_unit' => $businessUnit,
                    
                    'date_of_join' => $doj,

                    'address' => $address,

                    'error' => 'Email already exists.',

                ]);

                continue;

            }

            if (
                NonEmployee::query()
                    ->where(
                        'ktp',
                        $citizenNumber
                    )
                    ->exists()
            ) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'business_unit' => $businessUnit,
                    
                    'date_of_join' => $doj,

                    'address' => $address,

                    'error' => 'Citizenship Number / Passport ID already exists.',

                ]);

                continue;

            }

        }

        if ($errors->isNotEmpty()) {

            session()->put(
                'credential_import_errors',
                $errors
            );

            throw ValidationException::withMessages([

                'file' => sprintf(
                    '%d row(s) failed validation. Please download the error report.',
                    $errors->count(),
                ),

            ]);

        }

        /*
        |--------------------------------------------------------------------------
        | Import
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use ($rows) {

            foreach ($rows as $row) {

                $password = Str::password(12);

                $user = NonEmployeeUser::query()->create([

                    'name' => trim($row['fullname']),

                    'email' => trim($row['email']),

                    // KTP is the only identity stable across promotion: email
                    // moves to the office domain and ids differ per database.
                    // 'citizen_number' => trim((string) $row['citizen_number_passport_number']),

                    'password' => Hash::make($password),

                ]);

                NonEmployee::query()->create([

                    'user_id' => $user->id,

                    'employee_id' => trim((string) ($row['nik'] ?? '')) ?: null,

                    'fullname' => trim($row['fullname']),

                    'email' => trim($row['email']),

                    'personal_mobile_number' => trim((string) ($row['phone_number'] ?? '')) ?: null,

                    'ktp' => trim((string) $row['citizen_number_passport_number']),

                    'nationality' => $this->resolveNationality($row['nationality'] ?? null),

                    'group_company' => trim($row['business_unit']),

                    // Office location the importer typed. Kept as-is; the
                    // work_area code is only filled when the area matches a
                    // location for that business unit, otherwise left empty.
                    'office_area' => trim((string) ($row['office_location'] ?? '')) ?: null,

                    'work_area_code' => Location::workAreaFor(
                        trim($row['business_unit']),
                        trim((string) ($row['office_location'] ?? '')),
                    ),

                    'date_of_joining' => $this->parseDate($row['date_of_join'] ?? null)?->format('Y-m-d'),

                    'permanent_address' => trim($row['permanent_address']),

                ]);

                DB::afterCommit(function () use (
                    $user,
                    $password
                ) {

                    Mail::to(
                        $user->email
                    )->queue(

                        new NonEmployeeCredentialMail(
                            $user,
                            $password
                        )

                    );

                });

            }

        });

    }
}