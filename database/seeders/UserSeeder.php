<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\NonEmployeeUser;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        NonEmployeeUser::query()->updateOrCreate(
            ['email' => 'admin@kpn.com'],
            [
                'employee_id' => 'ADM001',
                'name' => 'COI Administrator',
                'password' => Hash::make('password'),
            ]
        );

        NonEmployeeUser::query()->updateOrCreate(
            ['email' => 'manager@kpn.com'],
            [
                'employee_id' => 'MGR001',
                'name' => 'Department Manager',
                'password' => Hash::make('password'),
            ]
        );

        NonEmployeeUser::query()->updateOrCreate(
            ['email' => 'employee@kpn.com'],
            [
                'employee_id' => 'EMP001',
                'name' => 'Employee Test',
                'password' => Hash::make('password'),
            ]
        );
    }
}