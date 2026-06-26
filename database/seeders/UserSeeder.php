<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@kpn.com'],
            [
                'employee_id' => 'ADM001',
                'name' => 'COI Administrator',
                'citizen_number' => '3171000000000001',
                'role' => 'admin',
                'password' => Hash::make('password'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'manager@kpn.com'],
            [
                'employee_id' => 'MGR001',
                'name' => 'Department Manager',
                'citizen_number' => '3171000000000002',
                'role' => 'manager',
                'password' => Hash::make('password'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'employee@kpn.com'],
            [
                'employee_id' => 'EMP001',
                'name' => 'Employee Test',
                'citizen_number' => '3171000000000003',
                'role' => 'employee',
                'password' => Hash::make('password'),
            ]
        );
    }
}