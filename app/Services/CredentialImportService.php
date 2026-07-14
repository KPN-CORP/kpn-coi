<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\NonEmployeeCredentialMail;
use App\Models\NonEmployee;
use App\Models\NonEmployeeUser;
use Carbon\Carbon;
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

        // dd($rows);

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

            $nationality = trim(
                (string) $row['nationality']
            );

            $businessUnit = trim(
                (string) $row['business_unit']
            );
            $doj = trim(
                (string) $row['date_of_joining']
            );

            $address = trim(
                (string) $row['permanent_address']
            );

            if (blank($row['fullname'])) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'business_unit' => $businessUnit,
                    
                    'date_of_joining' => $doj,
                    
                    'citizen_number' => $citizenNumber,

                    'nationality' => $nationality,

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

                    'business_unit' => $businessUnit,
                    
                    'date_of_joining' => $doj,
                    
                    'citizen_number' => $citizenNumber,

                    'nationality' => $nationality,

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

                    'business_unit' => $businessUnit,
                    
                    'date_of_joining' => $doj,
                    
                    'citizen_number' => $citizenNumber,

                    'nationality' => $nationality,

                    'address' => $address,

                    'error' => 'Select Business Unit.',

                ]);

                continue;

            }

            if (blank($row['date_of_joining'])) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'business_unit' => $businessUnit,
                    
                    'date_of_joining' => $doj,
                    
                    'citizen_number' => $citizenNumber,

                    'nationality' => $nationality,

                    'address' => $address,

                    'error' => 'DOJ Cannot be Empty.',

                ]);

                continue;

            }

            try {

                $date = Carbon::createFromFormat(
                    'd-m-Y',
                    $doj
                );

                if (
                    $date->format('d-m-Y') !== $doj
                ) {
                    throw new \Exception();
                }

            } catch (\Throwable $e) {

                $errors->push([

                    'row' => $line,

                    'name' => $name,

                    'email' => $email,

                    'business_unit' => $businessUnit,
                    
                    'date_of_joining' => $doj,
                    
                    'citizen_number' => $citizenNumber,

                    'nationality' => $nationality,

                    'address' => $address,

                    'error' => 'Date of Joining must be in dd-mm-yyyy format.',

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

                    'business_unit' => $businessUnit,
                    
                    'date_of_joining' => $doj,
                    
                    'citizen_number' => $citizenNumber,

                    'nationality' => $nationality,

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

                    'business_unit' => $businessUnit,
                    
                    'date_of_joining' => $doj,
                    
                    'citizen_number' => $citizenNumber,

                    'nationality' => $nationality,

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

                    'business_unit' => $businessUnit,
                    
                    'date_of_joining' => $doj,
                    
                    'citizen_number' => $citizenNumber,

                    'nationality' => $nationality,

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

                    'business_unit' => $businessUnit,
                    
                    'date_of_joining' => $doj,
                    
                    'citizen_number' => $citizenNumber,

                    'nationality' => $nationality,

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

                    'password' => Hash::make($password),

                ]);

                NonEmployee::query()->create([

                    'id' => $user->id,

                    'fullname' => trim($row['fullname']),

                    'email' => trim($row['email']),

                    'ktp' => trim((string) $row['citizen_number_passport_number']),

                    'nationality' => Str::title(trim((string) $row['nationality'])),

                    'group_company' => trim($row['business_unit']),
                    
                    'date_of_joining' => Carbon::createFromFormat(
                                            'd-m-Y',
                                            trim($row['date_of_joining'])
                                        )->format('Y-m-d'),

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