<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

it('creates every enum permission', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::pluck('name')->sort()->values()->all())->toBe(collect(PermissionEnum::values())->sort()->values()->all());
});

it('keeps a locked super admin role with every permission', function () {
    $this->artisan('permissions:sync');

    $role = Role::findByName(config('baines.super_admin_role'));

    expect($role->is_system)->toBeTrue()
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(collect(PermissionEnum::values())->sort()->values()->all());
});

it('leaves admin-managed roles alone', function () {
    $this->artisan('permissions:sync');
    $role = Role::create(['name' => 'front-desk', 'guard_name' => 'web']);
    $role->givePermissionTo(PermissionEnum::UsersView);

    $this->artisan('permissions:sync');

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['users.view']);
});

it('is idempotent', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    $counts = [Permission::count(), Role::count(), DB::table('role_has_permissions')->count()];

    $this->artisan('permissions:sync')->assertSuccessful()->expectsOutputToContain('0 permission(s) created');

    expect([Permission::count(), Role::count(), DB::table('role_has_permissions')->count()])->toBe($counts);
});

it('reports orphans and only deletes them with --prune', function () {
    Permission::create(['name' => 'legacy.thing', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')->expectsOutputToContain('legacy.thing');
    expect(Permission::where('name', 'legacy.thing')->exists())->toBeTrue();

    $this->artisan('permissions:sync --prune');
    expect(Permission::where('name', 'legacy.thing')->exists())->toBeFalse();
});

it('checks permissions by enum and lets super admins pass every gate', function () {
    $this->artisan('permissions:sync');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::UsersView);

    $admin = User::factory()->role(config('baines.super_admin_role'))->create();

    expect($viewer->can(PermissionEnum::UsersView))->toBeTrue()
        ->and($viewer->can(PermissionEnum::UsersDelete))->toBeFalse()
        ->and($admin->can(PermissionEnum::UsersDelete))->toBeTrue()
        ->and($admin->isSuperAdmin())->toBeTrue();
});
