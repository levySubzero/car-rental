<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Artisan::call('permissions:sync');

        // Starting roles from PRD §3. They are ordinary roles: admins can rename, edit or delete them.
        // Their permissions are added as the modules they need are built (bookings, cars, ...).
        $this->role('operations', 'Booking desk: bookings, payments, handover and return.');
        $this->role('content-editor', 'Marketing: cars, photos, brands, services, policies and home page.');

        $this->staff('Baines Admin', 'admin@baines.test', config('baines.super_admin_role'));

        if (app()->isLocal()) {
            $this->staff('Operations Desk', 'ops@baines.test', 'operations');
            $this->staff('Content Editor', 'editor@baines.test', 'content-editor');
        }
    }

    private function role(string $name, string $description): void
    {
        Role::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['description' => $description],
        );
    }

    private function staff(string $name, string $email, string $role): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
                'is_staff' => true,
                'is_active' => true,
            ],
        );

        $user->syncRoles([$role]);
    }
}
