<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\NonEmployeeCredentialMail;
use App\Models\NonEmployee;
use App\Models\NonEmployeeUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CredentialImportService
{
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

            $citizenNumber = trim(
                (string) $row['citizen_number']
            );

            $gender = trim(
                (string) $row['gender']
            );

            if (blank($row['name'])) {

                $errors->push([

                    'row' => $line,

                    'name' => $row['name'],

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'gender' => $gender,

                    'address' => $row['address'],

                    'error' => 'Email already exists.',

                ]);

                continue;

            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $errors->push([

                    'row' => $line,

                    'name' => $row['name'],

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'gender' => $gender,

                    'address' => $row['address'],

                    'error' => 'Invalid email.',

                ]);

                continue;

            }

            if (
                !in_array(
                    $gender,
                    ['Male', 'Female']
                )
            ) {

                $errors->push([

                    'row' => $line,

                    'name' => $row['name'],

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'gender' => $gender,

                    'address' => $row['address'],

                    'error' => 'Gender must be Male or Female.',

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

                    'name' => $row['name'],

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'gender' => $gender,

                    'address' => $row['address'],

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

                    'name' => $row['name'],

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'gender' => $gender,

                    'address' => $row['address'],

                    'error' => 'Duplicate Citizenship Number in uploaded file.',

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

                    'name' => $row['name'],

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'gender' => $gender,

                    'address' => $row['address'],

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

                    'name' => $row['name'],

                    'email' => $email,

                    'citizen_number' => $citizenNumber,

                    'gender' => $gender,

                    'address' => $row['address'],

                    'error' => 'Citizenship Number already exists.',

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

                    'name' => trim($row['name']),

                    'email' => trim($row['email']),

                    'password' => Hash::make($password),

                ]);

                NonEmployee::query()->create([

                    'id' => $user->id,

                    'fullname' => trim($row['name']),

                    'email' => trim($row['email']),

                    'ktp' => trim($row['citizen_number']),

                    'gender' => trim($row['gender']),

                    'current_address' => trim($row['address']),

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