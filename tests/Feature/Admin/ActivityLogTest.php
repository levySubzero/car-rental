<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

it('lists activity with filters and diffs', function () {
    $viewer = staffWith(['activity.view']);
    $user = User::factory()->create(['name' => 'Before']);
    $user->update(['name' => 'After']);

    $this->actingAs($viewer)
        ->get(route('admin.activity.index', ['log' => 'users', 'event' => 'updated']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/activity/index')
            ->has('activities.data', 1)
            ->where('activities.data.0.changes.old.name', 'Before')
            ->where('activities.data.0.changes.attributes.name', 'After')
            ->where('activities.data.0.subject.label', 'After')
            ->has('options.logs'));
});

it('never logs passwords or 2FA secrets', function () {
    $user = User::factory()->create();
    $user->update(['password' => 'something-new-123', 'name' => 'Renamed']);

    $changes = Activity::query()->where('event', 'updated')->latest('id')->first()?->attribute_changes;

    expect(json_encode($changes))->not->toContain('password')->not->toContain('two_factor');
});

it('is forbidden without activity.view', function () {
    $this->actingAs(staffWith())->get(route('admin.activity.index'))->assertForbidden();
});
