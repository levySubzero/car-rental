<?php

use App\Enums\Permission;

it('splits values into module and ability', function () {
    expect(Permission::SettingsGeneralUpdate->module())->toBe('settings')
        ->and(Permission::SettingsGeneralUpdate->ability())->toBe('general.update')
        ->and(Permission::UsersAssignRoles->label())->toBe('Assign roles');
});

it('uses module.ability values in kebab case', function () {
    foreach (Permission::cases() as $permission) {
        expect($permission->value)->toMatch('/^[a-z][a-z-]*(\.[a-z][a-z-]*)+$/');
    }
});

it('selects permissions by module', function () {
    expect(Permission::forModule('activity'))->toBe([Permission::ActivityView])
        ->and(Permission::forModule('activity', 'system'))->toBe([Permission::ActivityView, Permission::SystemHorizon])
        ->and(Permission::forModule('nope'))->toBe([]);
});

it('removes excluded permissions', function () {
    expect(Permission::except(Permission::forModule('users'), Permission::UsersDelete))
        ->not->toContain(Permission::UsersDelete)
        ->toContain(Permission::UsersView);
});

it('groups names by module', function () {
    expect(Permission::grouped()['roles'])->toBe(['roles.view', 'roles.create', 'roles.update', 'roles.delete'])
        ->and(array_keys(Permission::grouped()))->toBe(Permission::modules());
});
