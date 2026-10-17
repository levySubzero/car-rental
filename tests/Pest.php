<?php

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit', 'Arch');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A staff member (2FA confirmed) holding exactly these permissions.
 *
 * @param  list<Permission|string>  $permissions
 */
function staffWith(array $permissions = []): User
{
    test()->artisan('permissions:sync');

    $user = User::factory()->staff()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function staffWithRole(string $role): User
{
    test()->artisan('permissions:sync');
    Role::findOrCreate($role, 'web');

    return User::factory()->staff()->role($role)->create();
}

function superAdmin(): User
{
    return staffWithRole(config('baines.super_admin_role'));
}
