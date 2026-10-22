<?php

namespace Database\Seeders;

use App\Enums\Permission;
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

        // Starting roles from PRD §3. They are ordinary roles: admins can rename, edit or delete them,
        // so permissions are only given when a role is first created. Each module adds its own.
        $this->role('operations', 'Booking desk: bookings, payments, handover and return.', [
            Permission::CarsView,
            Permission::AvailabilityManage,
            ...Permission::forModule('contact-messages'),
            Permission::PaymentMethodsView,
            // Pricing is the super admin's (PRD §3); the desk can see it and run quotes.
            Permission::FxRatesView,
            Permission::ChargesView,
            Permission::DeliveryZonesView,
            Permission::DepositBandsView,
            Permission::CancellationTiersView,
            Permission::QuotesSandbox,
        ]);
        $this->role('content-editor', 'Marketing: cars, photos, brands, services, policies and home page.', [
            ...Permission::forModule('brands', 'car-types'),
            ...Permission::except(Permission::forModule('cars'), Permission::CarsViewValue),
            ...Permission::forModule('services', 'pages', 'policies', 'payment-methods'),
            Permission::SettingsHomeUpdate,
            Permission::MediaUpload,
        ]);

        $this->staff('Baines Admin', 'admin@baines.test', config('baines.super_admin_role'));

        if (app()->isLocal()) {
            $this->staff('Operations Desk', 'ops@baines.test', 'operations');
            $this->staff('Content Editor', 'editor@baines.test', 'content-editor');
        }

        // The client's content: safe in every environment, never overwrites admin edits.
        $this->call(CmsSeeder::class);
        $this->call(PricingSeeder::class);
        $this->call(FleetSeeder::class);
        $this->call(SiteMediaSeeder::class);

        if (app()->environment(['local', 'staging'])) {
            $this->call(DemoCatalogSeeder::class);
        }
    }

    /**
     * @param  list<Permission>  $permissions
     */
    private function role(string $name, string $description, array $permissions = []): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['description' => $description],
        );

        if ($role->wasRecentlyCreated) {
            $role->givePermissionTo($permissions);
        }
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
