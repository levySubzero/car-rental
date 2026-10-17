<?php

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists roles with user and permission counts, super admin first', function () {
    $viewer = staffWith([Permission::RolesView]);
    $role = Role::create(['name' => 'front-desk', 'guard_name' => 'web']);
    User::factory()->staff()->create()->assignRole($role);

    $this->actingAs($viewer)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/roles/index')
            ->has('roles', 2)
            ->where('roles.0.is_system', true)
            ->where('roles.1.name', 'front-desk')
            ->where('roles.1.users_count', 1));
});

it('creates a role with permissions and logs it', function () {
    $admin = staffWith([Permission::RolesCreate]);

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'Front desk',
            'description' => 'Answers enquiries',
            'permissions' => ['users.view', 'activity.view'],
        ])
        ->assertRedirect();

    $role = Role::findByName('Front desk');

    expect($role->description)->toBe('Answers enquiries')
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['activity.view', 'users.view']);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'roles', 'subject_id' => $role->id, 'event' => 'created']);
});

it('validates role input', function () {
    staffWith();
    Role::create(['name' => 'taken', 'guard_name' => 'web']);

    $this->actingAs(staffWith([Permission::RolesCreate]))
        ->post(route('admin.roles.store'), ['name' => 'taken', 'permissions' => ['users.fly']])
        ->assertSessionHasErrors(['name', 'permissions.0']);
});

it('updates a role, and its users gain the new permissions', function () {
    $role = Role::create(['name' => 'front-desk', 'guard_name' => 'web']);
    $member = User::factory()->staff()->create();
    $member->assignRole($role);

    $this->actingAs(staffWith([Permission::RolesUpdate]))
        ->put(route('admin.roles.update', $role), ['name' => 'reception', 'description' => null, 'permissions' => ['activity.view']])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($role->fresh()->name)->toBe('reception')
        ->and($member->fresh()->can(Permission::ActivityView))->toBeTrue();
});

it('locks the super admin role, even for super admins', function () {
    $admin = superAdmin();
    $role = Role::findByName(config('baines.super_admin_role'));

    $this->actingAs($admin)->put(route('admin.roles.update', $role), ['name' => 'boss', 'permissions' => []]);
    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role));

    expect($role->fresh())->not->toBeNull()
        ->and($role->fresh()->name)->toBe(config('baines.super_admin_role'))
        ->and($role->fresh()->permissions)->not->toBeEmpty();
});

it('refuses to delete a role that is still assigned', function () {
    $role = Role::create(['name' => 'front-desk', 'guard_name' => 'web']);
    User::factory()->staff()->create()->assignRole($role);

    $this->actingAs(staffWith([Permission::RolesDelete]))->delete(route('admin.roles.destroy', $role));

    expect($role->fresh())->not->toBeNull();
});

it('deletes an unused role', function () {
    $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);

    $this->actingAs(staffWith([Permission::RolesDelete]))
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::find($role->id))->toBeNull();
});

it('needs the matching permission for each action', function () {
    $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);
    $viewer = staffWith([Permission::RolesView]);

    $this->actingAs($viewer)->get(route('admin.roles.create'))->assertForbidden();
    $this->actingAs($viewer)->put(route('admin.roles.update', $role), ['name' => 'x', 'permissions' => []])->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.roles.destroy', $role))->assertForbidden();
});
