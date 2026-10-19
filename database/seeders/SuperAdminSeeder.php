<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives existing employee accounts the Super Admin role (every permission,
 * Role Management included), e.g. to get into a fresh stage database:
 *
 *   SUPER_ADMIN_EMAILS=someone@kpn-corp.com,other@kpn-corp.com
 *   SUPER_ADMIN_PASSWORD=...   (optional: also sets their login password)
 *
 *   php artisan db:seed --class=SuperAdminSeeder
 *
 * Accounts are looked up, never created: an employee account lives in the
 * HRIS database (kpncorp.users) with a matching employees row, which this
 * app does not own. The role itself is kept on the app database; assignRole
 * writes there even though User is on kpncorp (see RoleController).
 *
 * Safe to run again: roles and permissions are created only when missing and
 * a role already held is not added twice.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $emails = config('auth.super_admin.emails', []);
        $password = config('auth.super_admin.password');

        if ($emails === []) {
            $this->command?->warn('SUPER_ADMIN_EMAILS is empty in .env -- nothing to do. (Changed .env? Run php artisan config:clear.)');

            return;
        }

        // Super Admin with every permission, including any added since.
        $this->call(PermissionSeeder::class);

        foreach ($emails as $email) {
            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                $this->command?->error("{$email}: no employee account with this email in the HRIS users table -- skipped.");

                continue;
            }

            $user->assignRole('Super Admin');

            if (filled($password)) {
                // Hashed by the User model's 'hashed' cast.
                $user->forceFill(['password' => $password])->save();
            }

            $this->command?->info("{$email} (user {$user->id}): Super Admin".(filled($password) ? ', password set' : ''));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
