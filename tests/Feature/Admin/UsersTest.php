<?php

use App\Models\Role;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Role::findOrCreate('operations', 'web');
    Role::findOrCreate('content-editor', 'web');
});

describe('listing', function () {
    it('lists staff with filters', function () {
        $admin = staffWith(['users.view']);
        User::factory()->staff()->create(['name' => 'Wanjiku Kamau']);
        User::factory()->create(['name' => 'Not Staff']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Wanjiku']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Wanjiku Kamau')
                ->has('users.meta.links')
                ->where('filters.search', 'Wanjiku'));
    });

    it('is forbidden without users.view', function () {
        $this->actingAs(staffWith())->get(route('admin.users.index'))->assertForbidden();
    });

    it('rejects unknown filter values', function () {
        $this->actingAs(staffWith(['users.view']))
            ->get(route('admin.users.index', ['role' => 'emperor']))
            ->assertSessionHasErrors('role');
    });
});

describe('inviting', function () {
    it('creates a staff account and emails an invitation', function () {
        Notification::fake();
        $admin = staffWith(['users.create', 'users.assign-roles']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Otieno Ouma',
                'email' => 'otieno@baines.test',
                'roles' => ['operations'],
            ])
            ->assertRedirect();

        $user = User::where('email', 'otieno@baines.test')->firstOrFail();

        expect($user->is_staff)->toBeTrue()
            ->and($user->hasRole('operations'))->toBeTrue()
            ->and($user->hasVerifiedEmail())->toBeFalse();

        Notification::assertSentTo($user, StaffInvitation::class);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $user->id, 'event' => 'invited', 'causer_id' => $admin->id]);
    });

    it('needs users.assign-roles to set roles', function () {
        $this->actingAs(staffWith(['users.create']))
            ->post(route('admin.users.store'), [
                'name' => 'Otieno Ouma',
                'email' => 'otieno@baines.test',
                'roles' => [config('baines.super_admin_role')],
            ])
            ->assertForbidden();

        expect(User::where('email', 'otieno@baines.test')->exists())->toBeFalse();
    });

    it('validates the input', function () {
        User::factory()->create(['email' => 'taken@baines.test']);

        $this->actingAs(staffWith(['users.create', 'users.assign-roles', 'users.grant-permissions']))
            ->post(route('admin.users.store'), [
                'name' => '',
                'email' => 'taken@baines.test',
                'roles' => ['emperor'],
                'permissions' => ['users.fly'],
            ])
            ->assertSessionHasErrors(['name', 'email', 'roles.0', 'permissions.0']);
    });

    it('is forbidden without users.create', function () {
        $viewer = staffWith(['users.view']);

        $this->actingAs($viewer)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.users.store'), [])->assertForbidden();
    });

    it('resends an invitation', function () {
        Notification::fake();
        $user = User::factory()->staff()->unverified()->create();

        $this->actingAs(staffWith(['users.update']))
            ->post(route('admin.users.resend-invitation', $user))
            ->assertRedirect();

        Notification::assertSentTo($user, StaffInvitation::class);
    });
});

describe('editing', function () {
    it('shows the edit page with access options', function () {
        $viewer = staffWith(['users.view']);
        $user = User::factory()->staff()->role('operations')->create();

        $this->actingAs($viewer)
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/edit')
                ->where('user.roles', ['operations'])
                ->has('permissionOptions.users'));
    });

    it('updates details and unverifies a changed email', function () {
        $user = User::factory()->staff()->create();

        $this->actingAs(staffWith(['users.update']))
            ->put(route('admin.users.update', $user), ['name' => 'New Name', 'email' => 'new@baines.test'])
            ->assertRedirect();

        expect($user->fresh())
            ->name->toBe('New Name')
            ->email_verified_at->toBeNull();
    });

    it('syncs roles and direct permissions and logs the change', function () {
        $admin = staffWith(['users.assign-roles', 'users.grant-permissions']);
        $user = User::factory()->staff()->role('operations')->create();

        $this->actingAs($admin)
            ->put(route('admin.users.access', $user), ['roles' => ['content-editor'], 'permissions' => ['activity.view']])
            ->assertRedirect();

        $user->refresh();

        expect($user->getRoleNames()->all())->toBe(['content-editor'])
            ->and($user->getDirectPermissions()->pluck('name')->all())->toBe(['activity.view']);

        $this->assertDatabaseHas('activity_log', ['subject_id' => $user->id, 'event' => 'access-changed']);
    });

    it('leaves roles alone when only permissions are sent', function () {
        $admin = staffWith(['users.grant-permissions']);
        $user = User::factory()->staff()->role('operations')->create();

        $this->actingAs($admin)
            ->put(route('admin.users.access', $user), ['permissions' => []])
            ->assertRedirect();

        expect($user->fresh()->hasRole('operations'))->toBeTrue();
    });

    it('needs the matching permission for each part of access', function () {
        $admin = staffWith(['users.grant-permissions']);
        $user = User::factory()->staff()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.access', $user), ['roles' => ['operations']])
            ->assertForbidden();
    });
});

describe('safety rules', function () {
    it('keeps at least one super admin', function () {
        $admin = superAdmin();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->put(route('admin.users.access', $admin), ['roles' => []])
            ->assertRedirect(route('admin.users.edit', $admin));

        expect($admin->fresh()->isSuperAdmin())->toBeTrue();
    });

    it('stops anyone removing the last super admin', function () {
        $actor = staffWith(['users.assign-roles', 'users.update', 'users.delete']);
        $onlySuperAdmin = superAdmin();

        $this->actingAs($actor)->put(route('admin.users.access', $onlySuperAdmin), ['roles' => []]);
        $this->actingAs($actor)->put(route('admin.users.toggle-active', $onlySuperAdmin), ['active' => false]);
        $this->actingAs($actor)->delete(route('admin.users.destroy', $onlySuperAdmin));

        $fresh = $onlySuperAdmin->fresh();

        expect($fresh)->not->toBeNull()
            ->and($fresh->is_active)->toBeTrue()
            ->and($fresh->isSuperAdmin())->toBeTrue();
    });

    it('prevents deactivating or deleting yourself', function () {
        $admin = superAdmin();
        superAdmin();

        $this->actingAs($admin)->put(route('admin.users.toggle-active', $admin), ['active' => false]);
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $fresh = $admin->fresh();

        expect($fresh)->not->toBeNull()
            ->and($fresh->is_active)->toBeTrue();
    });

    it('deactivates and deletes other users', function () {
        $admin = staffWith(['users.update', 'users.delete']);
        $user = User::factory()->staff()->create();

        $this->actingAs($admin)->put(route('admin.users.toggle-active', $user), ['active' => false])->assertRedirect();
        expect($user->fresh()->is_active)->toBeFalse();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));
        expect(User::find($user->id))->toBeNull();
    });
});
